<?php

/**
 * ExoOnLine
 * Copyright or © or Copr. Université Jean Monnet (France), 2012
 * dsi.dev@univ-st-etienne.fr
 *
 * This software is a computer program whose purpose is to [describe
 * functionalities and technical features of your software].
 *
 * This software is governed by the CeCILL license under French law and
 * abiding by the rules of distribution of free software.  You can  use,
 * modify and/ or redistribute the software under the terms of the CeCILL
 * license as circulated by CEA, CNRS and INRIA at the following URL
 * "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and  rights to copy,
 * modify and redistribute granted by the license, users are provided only
 * with a limited warranty  and the software's author,  the holder of the
 * economic rights,  and the successive licensors  have only  limited
 * liability.
 *
 * In this respect, the user's attention is drawn to the risks associated
 * with loading,  using,  modifying and/or developing or reproducing the
 * software by the user in light of its specific status of free software,
 * that may mean  that it is complicated to manipulate,  and  that  also
 * therefore means  that it is reserved for developers  and  experienced
 * professionals having in-depth computer knowledge. Users are therefore
 * encouraged to load and test the software's suitability as regards their
 * requirements in conditions enabling the security of their systems and/or
 * data to be ensured and,  more generally, to use and operate it in the
 * same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
*/

namespace UJM\ExoBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\DomCrawler\Crawler;

//use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;

use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;

use UJM\ExoBundle\Entity\Question;
use UJM\ExoBundle\Entity\Category;
use UJM\ExoBundle\Entity\Coords;

use UJM\ExoBundle\Entity\Choice;
use UJM\ExoBundle\Form\QuestionType;

use UJM\ExoBundle\Entity\InteractionQCM;
use UJM\ExoBundle\Form\InteractionQCMType;

use UJM\ExoBundle\Entity\InteractionGraphic;
use UJM\ExoBundle\Form\InteractionGraphicType;

use UJM\ExoBundle\Entity\InteractionOpen;
use UJM\ExoBundle\Form\InteractionOpenType;

use UJM\ExoBundle\Entity\InteractionHole;
use UJM\ExoBundle\Form\InteractionHoleType;

use UJM\ExoBundle\Entity\Interaction;
use UJM\ExoBundle\Entity\Share;

use UJM\ExoBundle\Entity\Response;
use UJM\ExoBundle\Form\ResponseType;
use Claroline\CoreBundle\Entity\Resource\ResourceNode;
use UJM\ExoBundle\Entity\Document;

use UJM\ExoBundle\Repository\InteractionGraphicRepository;
/**
 * Question controller.
 *
 */
class QuestionController extends Controller
{
    /**
     * Lists the User's Question entities.
     *
     */
    public function indexAction($pageNow = 0, $pageNowShared = 0, $categoryToFind = '', $titleToFind = '', $resourceId = -1, $displayAll = 0, $idExo = -1, $QuestionsExo = 'false')
    {
        if ($QuestionsExo == '') {
            $QuestionsExo = 'false';
        }

        $vars = array();
        $sharedWithMe = array();
        $shareRight = array();
        $questionWithResponse = array();
        $alreadyShared = array();

        $em = $this->getDoctrine()->getManager();

        if ($resourceId != -1) {
            $exercise = $em->getRepository('UJMExoBundle:Exercise')->find($resourceId);
            $vars['_resource'] = $exercise;
        }

        // To paginate the result :
        $request = $this->get('request'); // Get the request which contains the following parameters :
        $page = $request->query->get('page', 1); // Get the choosen page (default 1)
        $click = $request->query->get('click', 'my'); // Get which array to fchange page (default 'my question')
        $pagerMy = $request->query->get('pagerMy', 1); // Get the page of the array my question (default 1)
        $pagerShared = $request->query->get('pagerShared', 1); // Get the pager of the array my shared question (default 1)
        $max = 10; // Max of questions per page

        // If change page of my questions array
        if ($click == 'my') {
            // The choosen new page is for my questions array
            $pagerMy = $page;
        // Else if change page of my shared questions array
        } else if ($click == 'shared') {
            // The choosen new page is for my shared questions array
            $pagerShared = $page;
        }

        $user = $this->container->get('security.context')->getToken()->getUser();
        $uid = $user->getId();

        if ($QuestionsExo == 'true') {

            $actionQ = array();

            $listQExo = $this->getDoctrine()
                ->getManager()
                ->getRepository('UJMExoBundle:Interaction')
                ->getExerciseInteraction($em, $idExo, 0);

            foreach ($listQExo as $interaction) {
                if ($interaction->getQuestion()->getUser()->getId() == $uid) {
                    $actionQ[$interaction->getQuestion()->getId()] = 1; // my question

                    $actions = $this->getActionInteraction($em, $interaction);
                    $questionWithResponse += $actions[0];
                    $alreadyShared += $actions[1];
                } else {
                    $sharedQ = $em->getRepository('UJMExoBundle:Share')
                    ->findOneBy(array('user' => $uid, 'question' => $interaction->getQuestion()->getId()));

                    if (count($sharedQ) > 0) {
                        $actionQ[$interaction->getQuestion()->getId()] = 2; // shared question

                        $actionsS = $this->getActionShared($em, $sharedQ);
                        $sharedWithMe += $actionsS[0];
                        $shareRight += $actionsS[1];
                        $questionWithResponse += $actionsS[2];
                    } else {
                        $actionQ[$interaction->getQuestion()->getId()] = 3; // other
                    }

                }
            }
        } else {
            $interactions = $this->getDoctrine()
                ->getManager()
                ->getRepository('UJMExoBundle:Interaction')
                ->getUserInteraction($uid);

            foreach ($interactions as $interaction) {
                $actions = $this->getActionInteraction($em, $interaction);
                $questionWithResponse += $actions[0];
                $alreadyShared += $actions[1];
            }

            $shared = $em->getRepository('UJMExoBundle:Share')
                ->findBy(array('user' => $uid));

            foreach ($shared as $s) {
                $actionsS = $this->getActionShared($em, $s);
                $sharedWithMe += $actionsS[0];
                $shareRight += $actionsS[1];
                $questionWithResponse += $actionsS[2];
            }

            $doublePagination = $this->doublePaginationWithIf($interactions, $sharedWithMe, $max, $pagerMy, $pagerShared, $pageNow, $pageNowShared);

            $interactionsPager = $doublePagination[0];
            $pagerfantaMy = $doublePagination[1];

            $sharedWithMePager = $doublePagination[2];
            $pagerfantaShared = $doublePagination[3];
        }

        if ($categoryToFind != '' && $titleToFind != '' && $categoryToFind != 'z' && $titleToFind != 'z') {
            $i = 1 ;
            $pos = 0 ;
            $temp = 0;
            foreach ($interactions as $interaction) {
                if ($interaction->getQuestion()->getCategory() == $categoryToFind) {
                    $temp = $i;
                }
                if ($interaction->getQuestion()->getTitle() == $titleToFind && $temp == $i) {
                    $pos = $i;
                    break;
                }
                $i++;
            }

            if ($pos % $max == 0) {
                $pageNow = $pos / $max;
            } else {
                $pageNow = ceil($pos / $max);
            }
        }

        if ($displayAll == 1) {
            if (count($interactions) > count($shared)) {
                $max = count($interactions);
            } else {
                $max = count($shared);
            }
        }

        // Get the exercises created by the user to display questions linked to it
        $listExo = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:Exercise')
                        ->getExerciceByUser($uid);

        if ($QuestionsExo == 'false') {
            $vars['pagerMy']      = $pagerfantaMy;
            $vars['pagerShared']  = $pagerfantaShared;
            $vars['interactions'] = $interactionsPager;
            $vars['sharedWithMe'] = $sharedWithMePager;
        } else {
            $vars['listQExo'] = $listQExo;
            $vars['actionQ'] = $actionQ;
        }
        $vars['questionWithResponse'] = $questionWithResponse;
        $vars['alreadyShared']        = $alreadyShared;
        $vars['shareRight']           = $shareRight;
        $vars['displayAll']           = $displayAll;
        $vars['listExo']              = $listExo;
        $vars['idExo']                = $idExo;
        $vars['QuestionsExo']         = $QuestionsExo;

        return $this->render('UJMExoBundle:Question:index.html.twig', $vars);
    }

    /**
     * Finds and displays a Question entity.
     *
     */
    public function showAction($id, $exoID)
    {
        $vars = array();
        $allowToAccess = 0;

        if ($exoID != -1) {
            $exercise = $this->getDoctrine()->getManager()->getRepository('UJMExoBundle:Exercise')->find($exoID);
            $vars['_resource'] = $exercise;

            if ($this->container->get('ujm.exercise_services')
                     ->isExerciseAdmin($exercise)) {
                $allowToAccess = 1;
            }
        }

        $question = $this->controlUserQuestion($id);
        $sharedQuestion = $this->container->get('ujm.exercise_services')->controlUserSharedQuestion($id);

        if (count($question) > 0 || count($sharedQuestion) > 0 || $allowToAccess == 1) {
            $interaction = $this->getDoctrine()
                ->getManager()
                ->getRepository('UJMExoBundle:Interaction')
                ->getInteraction($id);

            $typeInter = $interaction[0]->getType();

            switch ($typeInter) {
                case "InteractionQCM":

                    $response = new Response();
                    $interactionQCM = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:InteractionQCM')
                        ->getInteractionQCM($interaction[0]->getId());

                    if ($interactionQCM[0]->getShuffle()) {
                        $interactionQCM[0]->shuffleChoices();
                    } else {
                        $interactionQCM[0]->sortChoices();
                    }

                    $form   = $this->createForm(new ResponseType(), $response);

                    $vars['interactionToDisplayed'] = $interactionQCM[0];
                    $vars['form']           = $form->createView();
                    $vars['exoID']          = $exoID;

                    return $this->render('UJMExoBundle:InteractionQCM:paper.html.twig', $vars);

                case "InteractionGraphic":

                    $interactionGraph = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:InteractionGraphic')
                        ->getInteractionGraphic($interaction[0]->getId());

                    $repository = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:Coords');

                    $listeCoords = $repository->findBy(array('interactionGraphic' => $interactionGraph[0]));

                    $vars['interactionToDisplayed'] = $interactionGraph[0];
                    $vars['listeCoords']        = $listeCoords;
                    $vars['exoID']              = $exoID;

                    return $this->render('UJMExoBundle:InteractionGraphic:paper.html.twig', $vars);

                case "InteractionHole":

                    $response = new Response();
                    $interactionHole = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:InteractionHole')
                        ->getInteractionHole($interaction[0]->getId());

                    $form   = $this->createForm(new ResponseType(), $response);

                    $vars['interactionToDisplayed'] = $interactionHole[0];
                    $vars['form']            = $form->createView();
                    $vars['exoID']           = $exoID;

                    return $this->render('UJMExoBundle:InteractionHole:paper.html.twig', $vars);

                case "InteractionOpen":
                    $response = new Response();
                    $interactionOpen = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:InteractionOpen')
                        ->getInteractionOpen($interaction[0]->getId());

                    $form   = $this->createForm(new ResponseType(), $response);

                    $vars['interactionToDisplayed'] = $interactionOpen[0];
                    $vars['form']            = $form->createView();
                    $vars['exoID']           = $exoID;

                    return $this->render('UJMExoBundle:InteractionOpen:paper.html.twig', $vars);

            }
        } else {
            return $this->redirect($this->generateUrl('ujm_question_index'));
        }
    }

    /**
     * Displays a form to create a new Question entity with interaction.
     *
     */
    public function newAction($exoID)
    {
        $variables = array(
            'exoID' => $exoID,
            'linkedCategory' =>  $this->container->get('ujm.exercise_services')->getLinkedCategories()
        );

        $em = $this->getDoctrine()->getManager();
        $exercise = $em->getRepository('UJMExoBundle:Exercise')->find($exoID);

        if ($exercise) {
            $variables['_resource'] = $exercise;
        }

        return $this->render('UJMExoBundle:Question:new.html.twig', $variables);
    }

    /**
     * Creates a new Question entity.
     *
     */
    public function createAction()
    {
        $entity  = new Question();
        $request = $this->getRequest();
        $form    = $this->createForm(new QuestionType(), $entity);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('question_show', array('id' => $entity->getId())));
        }

        return $this->render(
            'UJMExoBundle:Question:new.html.twig', array(
            'entity' => $entity,
            'form'   => $form->createView(),
            'linkedCategory' =>  $this->container->get('ujm.exercise_services')->getLinkedCategories()
            )
        );
    }

    /**
     * Displays a form to edit an existing Question entity.
     *
     */
    public function editAction($id, $exoID, $form = null)
    {
        $question = $this->controlUserQuestion($id);
        $share    = $this->container->get('ujm.exercise_services')->controlUserSharedQuestion($id);
        $user     = $this->container->get('security.context')->getToken()->getUser();
        $catID    = -1;

        if(count($share) > 0) {
            $shareAllowEdit = $share[0]->getAllowToModify();
        }

        if ( (count($question) > 0) || ($shareAllowEdit) ) {
            $interaction = $this->getDoctrine()
                ->getManager()
                ->getRepository('UJMExoBundle:Interaction')
                ->getInteraction($id);

            $typeInter = $interaction[0]->getType();

            $nbResponses = 0;
            $em = $this->getDoctrine()->getManager();
            $response = $em->getRepository('UJMExoBundle:Response')
                ->findBy(array('interaction' => $interaction[0]->getId()));
            $nbResponses = count($response);

            $linkedCategory = $this->container->get('ujm.exercise_services')->getLinkedCategories();

            if ($user->getId() != $interaction[0]->getQuestion()->getUser()->getId()) {
                $catID = $interaction[0]->getQuestion()->getCategory()->getId();
            }

            switch ($typeInter) {
                case "InteractionQCM":

                    $interactionQCM = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:InteractionQCM')
                        ->getInteractionQCM($interaction[0]->getId());
                    //fired a sort function
                    $interactionQCM[0]->sortChoices();

                    if ($form == null) {
                        $editForm = $this->createForm(
                            new InteractionQCMType(
                                $this->container->get('security.context')
                                    ->getToken()->getUser(), $catID
                            ), $interactionQCM[0]
                        );
                    } else {
                        $editForm = $form;
                    }
                    $deleteForm = $this->createDeleteForm($interactionQCM[0]->getId());
                    
                    $typeQCM = $this->getTypeQCM();

                    $variables['entity']         = $interactionQCM[0];
                    $variables['edit_form']      = $editForm->createView();
                    $variables['delete_form']    = $deleteForm->createView();
                    $variables['nbResponses']    = $nbResponses;
                    $variables['linkedCategory'] = $linkedCategory;
                    $variables['typeQCM'       ] = json_encode($typeQCM);
                    $variables['exoID']          = $exoID;

                    if ($exoID != -1) {
                        $exercise = $em->getRepository('UJMExoBundle:Exercise')->find($exoID);
                        $variables['_resource'] = $exercise;
                    }

                    return $this->render('UJMExoBundle:InteractionQCM:edit.html.twig', $variables);

                case "InteractionGraphic":
                    $docID = -1;
                    $interactionGraph = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:InteractionGraphic')
                        ->getInteractionGraphic($interaction[0]->getId());

                    $position = $em->getRepository('UJMExoBundle:Coords')->findBy(
                        array('interactionGraphic' => $interactionGraph[0]->getId()
                        )
                    );

                    if ($user->getId() != $interactionGraph[0]->getInteraction()->getQuestion()->getUser()->getId()) {
                        $docID = $interactionGraph[0]->getDocument()->getId();
                    }

                    $editForm = $this->createForm(
                        new InteractionGraphicType(
                            $this->container->get('security.context')
                                ->getToken()->getUser(), $catID, $docID
                        ), $interactionGraph[0]
                    );

                    $deleteForm = $this->createDeleteForm($interactionGraph[0]->getId());

                    $variables['entity']         = $interactionGraph[0];
                    $variables['edit_form']      = $editForm->createView();
                    $variables['delete_form']    = $deleteForm->createView();
                    $variables['nbResponses']    = $nbResponses;
                    $variables['linkedCategory'] = $linkedCategory;
                    $variables['position']       = $position;
                    $variables['exoID']          = $exoID;

                    if ($exoID != -1) {
                        $exercise = $em->getRepository('UJMExoBundle:Exercise')->find($exoID);
                        $variables['_resource'] = $exercise;
                    }

                    return $this->render('UJMExoBundle:InteractionGraphic:edit.html.twig', $variables);

                case "InteractionHole":
                    $interactionHole = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:InteractionHole')
                        ->getInteractionHole($interaction[0]->getId());

                    $editForm = $this->createForm(
                        new InteractionHoleType(
                            $this->container->get('security.context')
                                ->getToken()->getUser(), $catID
                        ), $interactionHole[0]
                    );
                    $deleteForm = $this->createDeleteForm($interactionHole[0]->getId());

                    return $this->render(
                        'UJMExoBundle:InteractionHole:edit.html.twig', array(
                        'entity'      => $interactionHole[0],
                        'edit_form'   => $editForm->createView(),
                        'delete_form' => $deleteForm->createView(),
                        'nbResponses' => $nbResponses,
                        'linkedCategory' => $linkedCategory,
                        'exoID' => $exoID
                        )
                    );

                case "InteractionOpen":

                    $interactionOpen = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:InteractionOpen')
                        ->getInteractionOpen($interaction[0]->getId());

                    $editForm = $this->createForm(
                        new InteractionOpenType(
                            $this->container->get('security.context')
                                ->getToken()->getUser(), $catID
                        ), $interactionOpen[0]
                    );
                    $deleteForm = $this->createDeleteForm($interactionOpen[0]->getId());

                    if ($exoID != -1) {
                        $exercise = $em->getRepository('UJMExoBundle:Exercise')->find($exoID);
                        $variables['_resource'] = $exercise;
                    }

                    $typeOpen = $this->getTypeOpen();
                    
                    $variables['entity']         = $interactionOpen[0];
                    $variables['edit_form']      = $editForm->createView();
                    $variables['delete_form']    = $deleteForm->createView();
                    $variables['nbResponses']    = $nbResponses;
                    $variables['linkedCategory'] = $linkedCategory;
                    $variables['typeOpen']       = json_encode($typeOpen);
                    $variables['exoID']          = $exoID;

                    if ($exoID != -1) {
                        $exercise = $em->getRepository('UJMExoBundle:Exercise')->find($exoID);
                        $variables['_resource'] = $exercise;
                    }

                    return $this->render('UJMExoBundle:InteractionOpen:edit.html.twig', $variables);

                    break;
            }
        } else {
            return $this->redirect($this->generateUrl('ujm_question_index'));
        }
    }

    /**
     * Edits an existing Question entity.
     *
     */
    public function updateAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('UJMExoBundle:Question')->find($id);

        if (!$entity) {
            throw $this->createNotFoundException('Unable to find Question entity.');
        }

        $editForm   = $this->createForm(new QuestionType(), $entity);
        $deleteForm = $this->createDeleteForm($id);

        $request = $this->getRequest();

        $editForm->handleRequest($request);

        if ($editForm->isValid()) {
            $em->persist($entity);
            $em->flush();

            return $this->redirect($this->generateUrl('question_edit', array('id' => $id)));
        }

        return $this->render(
            'UJMExoBundle:Question:edit.html.twig', array(
            'entity'      => $entity,
            'edit_form'   => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
            )
        );
    }

    /**
     * Deletes a Question entity.
     *
     */
    public function deleteAction($id, $pageNow, $maxPage, $nbItem, $lastPage)
    {
        $question = $this->controlUserQuestion($id);

        if (count($question) > 0) {
            $em = $this->getDoctrine()->getManager();

            $eq = $this->getDoctrine()
                ->getManager()
                ->getRepository('UJMExoBundle:ExerciseQuestion')
                ->getExercises($id);

            foreach ($eq as $e) {
                $em->remove($e);
            }

            $em->flush();

            $interaction = $this->getDoctrine()
                ->getManager()
                ->getRepository('UJMExoBundle:Interaction')
                ->getInteraction($id);

            $typeInter = $interaction[0]->getType();

             // If delete last item of page, display the previous one
            $rest = $nbItem % $maxPage;

            if ($rest == 1 && $pageNow == $lastPage) {
                $pageNow -= 1;
            }

            switch ($typeInter) {
                case "InteractionQCM":
                    $interactionQCM = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:InteractionQCM')
                        ->getInteractionQCM($interaction[0]->getId());

                    return $this->forward(
                        'UJMExoBundle:InteractionQCM:delete', array(
                            'id' => $interactionQCM[0]->getId(),
                            'pageNow' => $pageNow
                        )
                    );

                case "InteractionGraphic":
                    $interactionGraph = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:InteractionGraphic')
                        ->getInteractionGraphic($interaction[0]->getId());

                    return $this->forward(
                        'UJMExoBundle:InteractionGraphic:delete', array(
                            'id' => $interactionGraph[0]->getId(),
                            'pageNow' => $pageNow
                        )
                    );

                case "InteractionHole":
                    $interactionHole = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:InteractionHole')
                        ->getInteractionHole($interaction[0]->getId());

                    return $this->forward(
                        'UJMExoBundle:InteractionHole:delete', array(
                            'id' => $interactionHole[0]->getId(),
                            'pageNow' => $pageNow
                        )
                    );

                case "InteractionOpen":
                    $interactionOpen = $this->getDoctrine()
                    ->getManager()
                    ->getRepository('UJMExoBundle:InteractionOpen')
                    ->getInteractionOpen($interaction[0]->getId());

                    return $this->forward(
                        'UJMExoBundle:InteractionOpen:delete', array(
                            'id' => $interactionOpen[0]->getId(),
                            'pageNow' => $pageNow
                        )
                    );

                    break;
            }
        }
    }

    /**
     * Displays the rigth form when a teatcher wants to create a new Question (JS)
     *
     */
    public function choixFormTypeAction()
    {

        $request = $this->container->get('request');

        if ($request->isXmlHttpRequest()) {
            $valType = 0;

            $valType = $request->request->get('indice_type');
            $exoID = $request->request->get('exercise');

            if ($valType != 0) {
                //index 1 = Hole Question
                if ($valType == 1) {
                    $entity = new InteractionHole();
                    $form   = $this->createForm(
                        new InteractionHoleType(
                            $this->container->get('security.context')
                                ->getToken()->getUser()
                        ), $entity
                    );

                    return $this->container->get('templating')->renderResponse(
                        'UJMExoBundle:InteractionHole:new.html.twig', array(
                        'exoID'  => $exoID,
                        'entity' => $entity,
                        'form'   => $form->createView()
                        )
                    );
                }

                //index 1 = QCM Question
                if ($valType == 2) {
                    $entity = new InteractionQCM();
                    $form   = $this->createForm(
                        new InteractionQCMType(
                            $this->container->get('security.context')
                                ->getToken()->getUser()
                        ), $entity
                    );

                    $typeQCM = $this->getTypeQCM();
                    
                    return $this->container->get('templating')->renderResponse(
                        'UJMExoBundle:InteractionQCM:new.html.twig', array(
                        'exoID'   => $exoID,
                        'entity'  => $entity,
                        'typeQCM' => json_encode($typeQCM),
                        'form'    => $form->createView()
                        )
                    );
                }

                //index 1 = Graphic Question
                if ($valType == 3) {
                    $entity = new InteractionGraphic();
                    $form   = $this->createForm(
                        new InteractionGraphicType(
                            $this->container->get('security.context')
                                ->getToken()->getUser()
                        ), $entity
                    );

                    return $this->container->get('templating')->renderResponse(
                        'UJMExoBundle:InteractionGraphic:new.html.twig', array(
                        'exoID'  => $exoID,
                        'entity' => $entity,
                        'form'   => $form->createView()
                        )
                    );
                }

                //index 1 = Open Question
                if ($valType == 4) {
                    $entity = new InteractionOpen();
                    $form   = $this->createForm(
                        new InteractionOpenType(
                            $this->container->get('security.context')
                                ->getToken()->getUser()
                        ), $entity
                    );

                    $typeOpen = $this->getTypeOpen();

                    return $this->container->get('templating')->renderResponse(
                        'UJMExoBundle:InteractionOpen:new.html.twig', array(
                        'exoID'    => $exoID,
                        'entity'   => $entity,
                        'typeOpen' => json_encode($typeOpen),
                        'form'     => $form->createView()
                        )
                    );
                }
            }
        }
    }

    /**
     * To share Question
     *
     */
    public function shareAction($questionID)
    {
        return $this->render(
            'UJMExoBundle:Question:share.html.twig', array(
            'questionID' => $questionID
            )
        );
    }

    /**
     * To search Question
     *
     */
    public function searchAction()
    {
        $request = $this->get('request');

        $max = 10; // Max per page

        $search = $request->query->get('search');
        $page = $request->query->get('page');
        $questionID = $request->query->get('qId');

        if ($search != '') {
            $em = $this->getDoctrine()->getManager();
            $userList = $em->getRepository('ClarolineCoreBundle:User')->findByName($search);

            $pagination = $this->pagination($userList, $max, $page);

            $userListPager = $pagination[0];
            $pagerUserSearch = $pagination[1];

            // Put the result in a twig
            $divResultSearch = $this->render(
                'UJMExoBundle:Question:search.html.twig', array(
                'userList' => $userListPager,
                'pagerUserSearch' => $pagerUserSearch,
                'search' => $search,
                'questionID' => $questionID
                )
            );

            // If request is ajax (first display of the first search result (page = 1))
            if ($request->isXmlHttpRequest()) {
                return $divResultSearch; // Send the twig with the result
            } else {
                // Cut the header of the request to only have the twig with the result
                $divResultSearch = substr($divResultSearch, strrpos($divResultSearch, '<link'));

                // Send the form to search and the result
                return $this->render(
                    'UJMExoBundle:Question:share.html.twig', array(
                    'userList' => $userList,
                    'divResultSearch' => $divResultSearch,
                    'questionID' => $questionID
                    )
                );
            }

        } else {
            return $this->render(
                'UJMExoBundle:Question:search.html.twig', array(
                'userList' => '',
                )
            );
        }
    }

    /**
     * To manage the User's documents
     *
     */
    public function manageDocAction()
    {
        $user = $this->container->get('security.context')->getToken()->getUser();
        $request = $this->get('request');

        $repository = $this->getDoctrine()
            ->getManager()
            ->getRepository('UJMExoBundle:Document');

        $listDoc = $repository->findBy(array('user' => $user->getId()));

        // Pagination of the documents
        $max = 10; // Max questions displayed per page

        $page = $request->query->get('page', 1); // Which page

        $pagination = $this->pagination($listDoc, $max, $page);

        $listDocPager = $pagination[0];
        $pagerDoc= $pagination[1];

        return $this->render(
            'UJMExoBundle:Document:manageImg.html.twig',
            array(
                'listDoc' => $listDocPager,
                'pagerDoc' => $pagerDoc
            )
        );
    }

    /**
     * To delete a User's document
     *
     */
    public function deleteDocAction($label, $pageNow, $maxPage, $nbItem, $lastPage)
    {
        $dontdisplay = 0;

        $userId = $this->container->get('security.context')->getToken()->getUser()->getId();

        $repositoryDoc = $this->getDoctrine()
            ->getManager()
            ->getRepository('UJMExoBundle:Document');

        $listDoc = $repositoryDoc->findByLabel($label, $userId, 0);

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('UJMExoBundle:InteractionGraphic')->findBy(array('document' => $listDoc));

        if (!$entity) {

            $em->remove($listDoc[0]);
            $em->flush();

            $repository = $this->getDoctrine()
                ->getManager()
                ->getRepository('UJMExoBundle:Document');

            $listDoc = $repository->findBy(array('user' => $userId));

            if ($nbItem != 1) {
                // If delete last item of page, display the previous one
                $rest = $nbItem % $maxPage;

                if ($rest == 1 && $pageNow == $lastPage) {
                    $pageNow -= 1;
                }
            }

            $pagination = $this->pagination($listDoc, $maxPage, $pageNow);

            $listDocPager = $pagination[0];
            $pagerDoc = $pagination[1];

            return $this->render(
                'UJMExoBundle:Document:manageImg.html.twig',
                array(
                    'listDoc' => $listDocPager,
                    'pagerDoc' => $pagerDoc,
                )
            );

        } else {

            $questionWithResponse = array();
            $linkPaper = array();

            $request = $this->container->get('request');
            $max = 10;
            $page = $request->query->get('page', 1);
            $show = $request->query->get('show', 0);

            $end = count($entity);

            for ($i = 0; $i < $end; $i++) {

                $response = $em->getRepository('UJMExoBundle:Response')->findBy(
                    array('interaction' => $entity[$i]->getInteraction()->getId())
                );
                $paper = $em->getRepository('UJMExoBundle:ExerciseQuestion')->findBy(
                    array('question' => $entity[$i]->getInteraction()->getQuestion()->getId())
                );
            }

            if (count($response) > 0) {
                $questionWithResponse[$entity[$i]->getInteraction()->getId()] = 1;
                $dontdisplay = 1;
            } else {
                $questionWithResponse[$entity[$i]->getInteraction()->getId()] = 0;
            }

            if (count($paper) > 0) {
                $linkPaper[] = 1;
            } else {
                $linkPaper[] = 0;
            }

            $pagination = $this->pagination($entity, $max, $page);

            $entities = $pagination[0];
            $pagerDelDoc = $pagination[1];

            return $this->render(
                'UJMExoBundle:Document:safeDelete.html.twig',
                array(
                    'listGraph' => $entities,
                    'label' => $label,
                    'questionWithResponse' => $questionWithResponse,
                    'linkpaper' => $linkPaper,
                    'dontdisplay' => $dontdisplay,
                    'pagerDelDoc' => $pagerDelDoc,
                    'pageNow' => $pageNow,
                    'maxPage' => $maxPage,
                    'nbItem' => $nbItem,
                    'show' => $show
                )
            );
        }
    }

    /**
     * To delete a User's document linked to questions but not to paper
     *
     */
    public function deletelinkedDocAction($label)
    {
        $userId = $this->container->get('security.context')->getToken()->getUser()->getId();

        $repositoryDoc = $this->getDoctrine()
            ->getManager()
            ->getRepository('UJMExoBundle:Document');

        $listDoc = $repositoryDoc->findByLabel($label, $userId, 0);

        $em = $this->getDoctrine()->getManager();

        $entity = $em->getRepository('UJMExoBundle:InteractionGraphic')->findBy(array('document' => $listDoc));

        $end = count($entity);

        for ($i = 0; $i < $end; $i++) {

            $coords = $em->getRepository('UJMExoBundle:Coords')->findBy(array('interactionGraphic' => $entity[$i]->getId()));

            if (!$coords) {
                throw $this->createNotFoundException('Unable to find Coords link to interactiongraphic.');
            }

            $stop = count($coords);
            for ($x = 0; $x < $stop; $x++) {
                $em->remove($coords[$x]);
            }

            $em->remove($entity[$i]);
        }

        $em->remove($listDoc[0]);
        $em->flush();

        return $this->redirect($this->generateUrl('ujm_question_manage_doc'));
    }

    /**
     * To display the modal which allow to change the label of a document
     *
     */
    public function changeDocumentNameAction()
    {
        $request = $this->get('request'); // Get the request which contains the following parameters :
        $oldDocLabel = $request->request->get('oldDocLabel');
        $i = $request->request->get('i');

        return $this->render('UJMExoBundle:Document:changeName.html.twig', array('oldDocLabel' => $oldDocLabel, 'i' => $i));
    }

    /**
     * To change the label of a document
     *
     */
    public function updateNameAction()
    {
        $newlabel = $_POST['newlabel'];
        $oldlabel = $_POST['oldName'];

        $em = $this->getDoctrine()->getManager();

        $alterDoc = $em->getRepository('UJMExoBundle:Document')->findOneBy(array('label' => $oldlabel));

        $alterDoc->setLabel($newlabel);

        $em->persist($alterDoc);
        $em->flush();

        return new \Symfony\Component\HttpFoundation\Response($newlabel);
    }

    /**
     * To sort document by type
     *
     */
    public function sortDocumentsAction()
    {
        $request = $this->container->get('request');
        $user = $this->container->get('security.context')->getToken()->getUser();

        $max = 10; // Max per page

        $type = $request->query->get('doctype');
        $searchLabel = $request->query->get('searchLabel');
        $page = $request->query->get('page');

        if ($type && isset($searchLabel)) {
            $repository = $this->getDoctrine()
                ->getManager()
                ->getRepository('UJMExoBundle:Document');

            $listDocSort = $repository->findByType($type, $user->getId(), $searchLabel);

            $pagination = $this->pagination($listDocSort, $max, $page);

            $listDocSortPager = $pagination[0];
            $pagerSortDoc = $pagination[1];

            // Put the result in a twig
            $divResultSearch = $this->render(
                'UJMExoBundle:Document:sortDoc.html.twig', array(
                'listFindDoc' => $listDocSortPager,
                'pagerFindDoc' => $pagerSortDoc,
                'labelToFind' => $searchLabel,
                'whichAction' => 'sort',
                'doctype' => $type
                )
            );

            // If request is ajax (first display of the first search result (page = 1))
            if ($request->isXmlHttpRequest()) {
                return $divResultSearch; // Send the twig with the result
            } else {
                // Cut the header of the request to only have the twig with the result
                $divResultSearch = substr($divResultSearch, strrpos($divResultSearch, '<table'));

                // Send the form to search and the result
                return $this->render(
                    'UJMExoBundle:Document:manageImg.html.twig', array(
                    'divResultSearch' => $divResultSearch
                    )
                );
            }
        } else {
            return $this->render(
                'UJMExoBundle:Document:sortDoc.html.twig', array(
                'listFindDoc' => '',
                'whichAction' => 'sort'
                )
            );
        }
    }

    /**
     * To search document with a defined label
     *
     */
    public function searchDocAction()
    {
        $userId = $this->container->get('security.context')->getToken()->getUser()->getId();
        $request = $this->get('request');

        $max = 10; // Max per page

        $labelToFind = $request->query->get('labelToFind');
        $page = $request->query->get('page');

        if ($labelToFind) {
            $em = $this->getDoctrine()->getManager();
            $listFindDoc = $em->getRepository('UJMExoBundle:Document')->findByLabel($labelToFind, $userId, 1);

            $pagination = $this->pagination($listFindDoc, $max, $page);

            $listFindDocPager = $pagination[0];
            $pagerFindDoc = $pagination[1];

            // Put the result in a twig
            $divResultSearch = $this->render(
                'UJMExoBundle:Document:sortDoc.html.twig', array(
                'listFindDoc' => $listFindDocPager,
                'pagerFindDoc' => $pagerFindDoc,
                'labelToFind' => $labelToFind,
                'whichAction' => 'search'
                )
            );

            // If request is ajax (first display of the first search result (page = 1))
            if ($request->isXmlHttpRequest()) {
                return $divResultSearch; // Send the twig with the result
            } else {
                // Cut the header of the request to only have the twig with the result
                $divResultSearch = substr($divResultSearch, strrpos($divResultSearch, '<table'));

                // Send the form to search and the result
                return $this->render(
                    'UJMExoBundle:Document:manageImg.html.twig', array(
                    'divResultSearch' => $divResultSearch
                    )
                );
            }
        } else {
            return $this->render(
                'UJMExoBundle:Document:sortDoc.html.twig', array(
                'listFindDoc' => '',
                'whichAction' => 'search'
                )
            );
        }
    }


    /**
     * To share question with other users
     *
     */
    public function shareQuestionUserAction()
    {

        $request = $this->container->get('request');

        if ($request->isXmlHttpRequest()) {
            $questionID = $request->request->get('questionID'); // Which question is shared

            $uid = $request->request->get('uid');
            $allowToModify = $request->request->get('allowToModify');

            $em = $this->getDoctrine()->getManager();

            $question = $em->getRepository('UJMExoBundle:Question')->findOneBy(array('id' => $questionID));
            $user     = $em->getRepository('ClarolineCoreBundle:User')->find($uid);

            $share = $em->getRepository('UJMExoBundle:Share')->findOneBy(array('user' => $user, 'question' => $question));

            if (!$share) {
                $share = new Share($user, $question);
            }

            $share->setAllowToModify($allowToModify);

            $em->persist($share);
            $em->flush();

            return new \Symfony\Component\HttpFoundation\Response('no;'.$this->generateUrl('ujm_question_index'));

        }
    }

    /**
     * If question already shared with a given user
     *
     */
    public function alreadySharedAction($toShare, $em)
    {
        $alreadyShared = $em->getRepository('UJMExoBundle:Share')->findAll();
        $already = false;

        $end = count($alreadyShared);

        for ($i = 0; $i < $end; $i++) {
            if ($alreadyShared[$i]->getUser() == $toShare->getUser() &&
                $alreadyShared[$i]->getQuestion() == $toShare->getQuestion()
            ) {
                $already = true;
                break;
            }
        }

        if ($already == true) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Display form to search questions
     *
     */
    public function searchQuestionAction($exoID)
    {
        return $this->render('UJMExoBundle:Question:searchQuestion.html.twig', array(
            'exoID' => $exoID
            )
        );
    }

    /**
     * Display the questions matching to the research
     *
     */
    public function searchQuestionTypeAction()
    {
        $user = $this->container->get('security.context')->getToken()->getUser();
        $request = $this->get('request');

        $listInteractions = array();
        $questionWithResponse = array();
        $alreadyShared = array();

        $max = 10; // Max questions displayed per page

        $type = $request->query->get('type'); // In which column
        $whatToFind = $request->query->get('whatToFind'); // Which text to find
        $where = $request->query->get('where'); // In which database
        $page = $request->query->get('page'); // Which page
        $exoID = $request->query->get('exoID'); // If we import or see the questions
        $displayAll = $request->query->get('displayAll', 0); // If we want to have all the questions in one page

//      echo $type . ' | '. $whatToFind . ' | '. $where . ' | '. $page . ' | '. $exoID . ' | '. $displayAll;die();
//      b4 : All | i | all | 1 | 5 | 0


        // If what and where to search is defined
        if ($type && $whatToFind && $where) {
            $em = $this->getDoctrine()->getManager();
            $questionRepository = $em->getRepository('UJMExoBundle:Question');
            $interactionRepository = $em->getRepository('UJMExoBundle:Interaction');

            // Get the matching questions depending on :
            //  * in which database search,
            //  * in witch column
            //  * and what text to search

            // User's database
            if ($where == 'my') {
                switch ($type) {
                    case 'Category':
                        $questions = $questionRepository->findByCategory($user->getId(), $whatToFind);

                        $end = count($questions);

                        for ($i = 0; $i < $end; $i++) {
                            $listInteractions[] = $interactionRepository->findOneBy(array('question' => $questions[$i]->getId()));
                        }
                        break;

                    case 'Type':
                        $listInteractions = $interactionRepository->findByType($user->getId(), $whatToFind);
                        break;

                    case 'Title':
                        $questions = $questionRepository->findByTitle($user->getId(), $whatToFind);

                        $end = count($questions);

                        for ($i = 0; $i < $end; $i++) {
                            $listInteractions[] = $interactionRepository->findOneBy(array('question' => $questions[$i]->getId()));
                        }
                        break;

                    case 'Contain':
                        $listInteractions = $interactionRepository->findByContain($user->getId(), $whatToFind);
                        break;

                    case 'All':
                        $listInteractions = $interactionRepository->findByAll($user->getId(), $whatToFind);
                        break;
                }

                // For all the matching interactions search if ...
                foreach ($listInteractions as $interaction) {
                    // ... the interaction is link to a paper (interaction in the test has already been passed)
                    $response = $em->getRepository('UJMExoBundle:Response')
                        ->findBy(array('interaction' => $interaction->getId()));
                    if (count($response) > 0) {
                        $questionWithResponse[$interaction->getId()] = 1;
                    } else {
                        $questionWithResponse[$interaction->getId()] = 0;
                    }

                    // ...the interaction is shared or not
                    $share = $em->getRepository('UJMExoBundle:Share')
                        ->findBy(array('question' => $interaction->getQuestion()->getId()));
                    if (count($share) > 0) {
                        $alreadyShared[$interaction->getQuestion()->getId()] = 1;
                    } else {
                        $alreadyShared[$interaction->getQuestion()->getId()] = 0;
                    }
                }

                if ($exoID == -1) {

                    if ($displayAll == 1) {
                        $max = count($listInteractions);
                    }

                    $pagination = $this->pagination($listInteractions, $max, $page);
                } else {
                    $exoQuestions = $em->getRepository('UJMExoBundle:ExerciseQuestion')->findBy(array('exercise' => $exoID));

                    $finalList = array();
                    $length = count($listInteractions);
                    $already = false;

                    for ($i = 0; $i < $length; $i++) {
                        foreach ($exoQuestions as $exoQuestion) {
                            if ($exoQuestion->getQuestion()->getId() == $listInteractions[$i]->getQuestion()->getId()) {
                                $already = true;
                                break;
                            }
                        }
                        if ($already == false) {
                            $finalList[] = $listInteractions[$i];
                        }
                        $already = false;
                    }

                    if ($displayAll == 1) {
                        $max = count($finalList);
                    }

                    $pagination = $this->pagination($finalList, $max, $page);
                }

                $listQuestionsPager = $pagination[0];
                $pagerSearch = $pagination[1];

                // Put the result in a twig
                if ($exoID == -1) {
                    $divResultSearch = $this->render(
                        'UJMExoBundle:Question:SearchQuestionType.html.twig', array(
                        'listQuestions' => $listQuestionsPager,
                        'canDisplay' => $where,
                        'pagerSearch' => $pagerSearch,
                        'type'        => $type,
                        'whatToFind'  => $whatToFind,
                        'questionWithResponse' => $questionWithResponse,
                        'alreadyShared' => $alreadyShared,
                        'exoID' => $exoID,
                        'displayAll' => $displayAll
                        )
                    );
                } else {
                    $divResultSearch = $this->render(
                        'UJMExoBundle:Question:searchQuestionImport.html.twig', array(
                            'listQuestions' => $listQuestionsPager,
                            'pagerSearch'   => $pagerSearch,
                            'exoID'         => $exoID,
                            'canDisplay'    => $where,
                            'whatToFind'    => $whatToFind,
                            'type'          => $type,
                            'displayAll'    => $displayAll
                        )
                    );
                }

                // If request is ajax (first display of the first search result (page = 1))
                if ($request->isXmlHttpRequest()) {
                    return $divResultSearch; // Send the twig with the result
                } else {
                    // Cut the header of the request to only have the twig with the result
                    $divResultSearch = substr($divResultSearch, strrpos($divResultSearch, '<link'));

                    // Send the form to search and the result
                    return $this->render(
                        'UJMExoBundle:Question:searchQuestion.html.twig', array(
                        'divResultSearch' => $divResultSearch,
                        'exoID' => $exoID
                        )
                    );
                }
            // Shared with user's database
            } else if ($where == 'shared') {
                switch ($type) {
                    case 'Category':
                        $sharedQuestion = $em->getRepository('UJMExoBundle:Share')
                            ->findByCategoryShared($user->getId(), $whatToFind);

                        $end = count($sharedQuestion);

                        for ($i = 0; $i < $end; $i++) {
                            $listInteractions[] = $em->getRepository('UJMExoBundle:Interaction')
                                ->findOneBy(array('question' => $sharedQuestion[$i]->getQuestion()->getId()));
                        }
                        break;

                    case 'Type':
                        $sharedQuestion = $em->getRepository('UJMExoBundle:Share')
                            ->findByTypeShared($user->getId(), $whatToFind);

                        $end = count($sharedQuestion);

                        for ($i = 0; $i < $end; $i++) {
                            $listInteractions[] = $em->getRepository('UJMExoBundle:Interaction')
                                ->findOneBy(array('question' => $sharedQuestion[$i]->getQuestion()->getId()));
                        }
                        break;

                    case 'Title':
                        $sharedQuestion = $em->getRepository('UJMExoBundle:Share')
                            ->findByTitleShared($user->getId(), $whatToFind);

                        $end = count($sharedQuestion);

                        for ($i = 0; $i < $end; $i++) {
                            $listInteractions[] = $em->getRepository('UJMExoBundle:Interaction')
                                ->findOneBy(array('question' => $sharedQuestion[$i]->getQuestion()->getId()));
                        }
                        break;

                    case 'Contain':
                        $sharedQuestion = $em->getRepository('UJMExoBundle:Share')
                            ->findByContainShared($user->getId(), $whatToFind);

                        $end = count($sharedQuestion);

                        for ($i = 0; $i < $end; $i++) {
                            $listInteractions[] = $em->getRepository('UJMExoBundle:Interaction')
                                ->findOneBy(array('question' => $sharedQuestion[$i]->getQuestion()->getId()));
                        }
                        break;

                    case 'All':
                        $sharedQuestion = $em->getRepository('UJMExoBundle:Share')
                            ->findByAllShared($user->getId(), $whatToFind);

                        $end = count($sharedQuestion);

                        for ($i = 0; $i < $end; $i++) {
                            $listInteractions[] = $em->getRepository('UJMExoBundle:Interaction')
                                ->findOneBy(array('question' => $sharedQuestion[$i]->getQuestion()->getId()));
                        }
                        break;
                }

                if ($exoID == -1) {

                    if ($displayAll == 1) {
                        $max = count($listInteractions);
                    }

                    $pagination = $this->pagination($listInteractions, $max, $page);
                } else {
                    $exoQuestions = $em->getRepository('UJMExoBundle:ExerciseQuestion')->findBy(array('exercise' => $exoID));

                    $finalList = array();
                    $length = count($listInteractions);
                    $already = false;

                    for ($i = 0; $i < $length; $i++) {
                        foreach ($exoQuestions as $exoQuestion) {
                            if ($exoQuestion->getQuestion()->getId() == $listInteractions[$i]->getQuestion()->getId()) {
                                $already = true;
                                break;
                            }
                        }
                        if ($already == false) {
                            $finalList[] = $listInteractions[$i];
                        }
                        $already = false;
                    }

                    if ($displayAll == 1) {
                        $max = count($finalList);
                    }

                    $pagination = $this->pagination($finalList, $max, $page);
                }

                $listQuestionsPager = $pagination[0];
                $pagerSearch = $pagination[1];

                // Put the result in a twig
                if ($exoID == -1) {
                    $divResultSearch = $this->render(
                        'UJMExoBundle:Question:SearchQuestionType.html.twig', array(
                        'listQuestions' => $listQuestionsPager,
                        'canDisplay' => $where,
                        'pagerSearch' => $pagerSearch,
                        'type'        => $type,
                        'whatToFind'  => $whatToFind,
                        'exoID' => $exoID,
                        'displayAll' => $displayAll
                        )
                    );
                } else {
                    $divResultSearch = $this->render(
                        'UJMExoBundle:Question:searchQuestionImport.html.twig', array(
                            'listQuestions' => $listQuestionsPager,
                            'pagerSearch'   => $pagerSearch,
                            'exoID'         => $exoID,
                            'canDisplay'    => $where,
                            'whatToFind'    => $whatToFind,
                            'type'          => $type,
                            'displayAll'    => $displayAll
                        )
                    );
                }

                // If request is ajax (first display of the first search result (page = 1))
                if ($request->isXmlHttpRequest()) {
                    return $divResultSearch; // Send the twig with the result
                } else {
                    // Cut the header of the request to only have the twig with the result
                    $divResultSearch = substr($divResultSearch, strrpos($divResultSearch, '<link'));

                    // Send the form to search and the result
                    return $this->render(
                        'UJMExoBundle:Question:searchQuestion.html.twig', array(
                        'divResultSearch' => $divResultSearch,
                        'exoID' => $exoID
                        )
                    );
                }
            } else if ($where == 'all') {
                switch ($type) {
                    case 'Category':
                        $questions = $questionRepository->findByCategory($user->getId(), $whatToFind);

                        $end = count($questions);

                        for ($i = 0; $i < $end; $i++) {
                            $listInteractions[] = $interactionRepository->findOneBy(array('question' => $questions[$i]->getId()));
                        }

                        $sharedQuestion = $em->getRepository('UJMExoBundle:Share')
                            ->findByCategoryShared($user->getId(), $whatToFind);

                        $ends = count($sharedQuestion);

                        for ($i = 0; $i < $ends; $i++) {
                            $listInteractions[] = $em->getRepository('UJMExoBundle:Interaction')
                                ->findOneBy(array('question' => $sharedQuestion[$i]->getQuestion()->getId()));
                        }
                        break;

                    case 'Type':
                        $listInteractions = $interactionRepository->findByType($user->getId(), $whatToFind);

                        $sharedQuestion = $em->getRepository('UJMExoBundle:Share')
                            ->findByTypeShared($user->getId(), $whatToFind);

                        $end = count($sharedQuestion);

                        for ($i = 0; $i < $end; $i++) {
                            $listInteractions[] = $em->getRepository('UJMExoBundle:Interaction')
                                ->findOneBy(array('question' => $sharedQuestion[$i]->getQuestion()->getId()));
                        }
                        break;

                    case 'Title':
                        $questions = $questionRepository->findByTitle($user->getId(), $whatToFind);

                        $end = count($questions);

                        for ($i = 0; $i < $end; $i++) {
                            $listInteractions[] = $interactionRepository->findOneBy(array('question' => $questions[$i]->getId()));
                        }

                        $sharedQuestion = $em->getRepository('UJMExoBundle:Share')
                            ->findByTitleShared($user->getId(), $whatToFind);

                        $ends = count($sharedQuestion);

                        for ($i = 0; $i < $ends; $i++) {
                            $listInteractions[] = $em->getRepository('UJMExoBundle:Interaction')
                                ->findOneBy(array('question' => $sharedQuestion[$i]->getQuestion()->getId()));
                        }
                        break;

                    case 'Contain':
                         $listInteractions = $interactionRepository->findByContain($user->getId(), $whatToFind);

                        $sharedQuestion = $em->getRepository('UJMExoBundle:Share')
                            ->findByContainShared($user->getId(), $whatToFind);

                        $end = count($sharedQuestion);

                        for ($i = 0; $i < $end; $i++) {
                            $listInteractions[] = $em->getRepository('UJMExoBundle:Interaction')
                                ->findOneBy(array('question' => $sharedQuestion[$i]->getQuestion()->getId()));
                        }
                        break;

                    case 'All':
                        $listInteractions = $interactionRepository->findByAll($user->getId(), $whatToFind);

                        $sharedQuestion = $em->getRepository('UJMExoBundle:Share')
                            ->findByAllShared($user->getId(), $whatToFind);

                        $end = count($sharedQuestion);

                        for ($i = 0; $i < $end; $i++) {
                            $listInteractions[] = $em->getRepository('UJMExoBundle:Interaction')
                                ->findOneBy(array('question' => $sharedQuestion[$i]->getQuestion()->getId()));
                        }
                        break;
                }

                // For all the matching interactions search if ...
                foreach ($listInteractions as $interaction) {
                    // ... the interaction is link to a paper (interaction in the test has already been passed)
                    $response = $em->getRepository('UJMExoBundle:Response')
                        ->findBy(array('interaction' => $interaction->getId()));
                    if (count($response) > 0) {
                        $questionWithResponse[$interaction->getId()] = 1;
                    } else {
                        $questionWithResponse[$interaction->getId()] = 0;
                    }

                    // ...the interaction is shared or not
                    $share = $em->getRepository('UJMExoBundle:Share')
                        ->findBy(array('question' => $interaction->getQuestion()->getId()));
                    if (count($share) > 0) {
                        $alreadyShared[$interaction->getQuestion()->getId()] = 1;
                    } else {
                        $alreadyShared[$interaction->getQuestion()->getId()] = 0;
                    }
                }

                if ($exoID == -1) {

                    if ($displayAll == 1) {
                        $max = count($listInteractions);
                    }

                    $pagination = $this->pagination($listInteractions, $max, $page);
                } else {
                    $exoQuestions = $em->getRepository('UJMExoBundle:ExerciseQuestion')->findBy(array('exercise' => $exoID));

                    $finalList = array();
                    $length = count($listInteractions);
                    $already = false;

                    for ($i = 0; $i < $length; $i++) {
                        foreach ($exoQuestions as $exoQuestion) {
                            if ($exoQuestion->getQuestion()->getId() == $listInteractions[$i]->getQuestion()->getId()) {
                                $already = true;
                                break;
                            }
                        }
                        if ($already == false) {
                            $finalList[] = $listInteractions[$i];
                        }
                        $already = false;
                    }

                    if ($displayAll == 1) {
                        $max = count($finalList);
                    }

                    $pagination = $this->pagination($finalList, $max, $page);
                }

                $listQuestionsPager = $pagination[0];
                $pagerSearch = $pagination[1];

                // Put the result in a twig
                if ($exoID == -1) {
                    $divResultSearch = $this->render(
                        'UJMExoBundle:Question:SearchQuestionType.html.twig', array(
                        'listQuestions' => $listQuestionsPager,
                        'canDisplay' => $where,
                        'pagerSearch' => $pagerSearch,
                        'type'        => $type,
                        'whatToFind'  => $whatToFind,
                        'questionWithResponse' => $questionWithResponse,
                        'alreadyShared' => $alreadyShared,
                        'exoID' => $exoID,
                        'displayAll' => $displayAll
                        )
                    );
                } else {
                    $divResultSearch = $this->render(
                        'UJMExoBundle:Question:searchQuestionImport.html.twig', array(
                            'listQuestions' => $listQuestionsPager,
                            'pagerSearch' => $pagerSearch,
                            'exoID' => $exoID,
                            'canDisplay' => $where,
                            'whatToFind'  => $whatToFind,
                            'type'        => $type,
                            'displayAll' => $displayAll
                        )
                    );
                }

                // If request is ajax (first display of the first search result (page = 1))
                if ($request->isXmlHttpRequest()) {
                    return $divResultSearch; // Send the twig with the result
                } else {
                    // Cut the header of the request to only have the twig with the result
                    $divResultSearch = substr($divResultSearch, strrpos($divResultSearch, '<link'));

                    // Send the form to search and the result
                    return $this->render(
                        'UJMExoBundle:Question:searchQuestion.html.twig', array(
                        'divResultSearch' => $divResultSearch,
                        'exoID' => $exoID
                        )
                    );
                }
            }
        } else {
            return $this->render(
                'UJMExoBundle:Question:SearchQuestionType.html.twig', array(
                'listQuestions' => '',
                'canDisplay' => $where,
                'whatToFind'  => $whatToFind,
                'type'        => $type
                )
            );
        }
    }

    /**
     * To delete the shared question of user's questions bank
     */
    public function deleteSharedQuestionAction($qid, $uid, $pageNow, $maxPage, $nbItem, $lastPage)
    {
        $em = $this->getDoctrine()->getManager();
        $sharedToDel = $em->getRepository('UJMExoBundle:Share')->findOneBy(array('question' => $qid, 'user' => $uid));

        if (!$sharedToDel) {
            throw $this->createNotFoundException('Unable to find Share entity.');
        }

        $em->remove($sharedToDel);
        $em->flush();

        // If delete last item of page, display the previous one
        $rest = $nbItem % $maxPage;

        if ($rest == 1 && $pageNow == $lastPage) {
            $pageNow -= 1;
        }

        return $this->redirect($this->generateUrl('ujm_question_index', array('pageNowShared' => $pageNow)));
    }

    /**
     * To see with which person the user has shared his question
     *
     */
    public function seeSharedWithAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $questionsharedWith = $em->getRepository('UJMExoBundle:Share')->findBy(array('question' => $id));

        $sharedWith = array();
        $stop = count($questionsharedWith);

        for ($i = 0; $i < $stop; $i++) {
            $sharedWith[] = $em->getRepository('ClarolineCoreBundle:User')->find($questionsharedWith[$i]->getUser()->getId());
        }

        return $this->render(
            'UJMExoBundle:Question:seeSharedWith.html.twig', array(
            'sharedWith' => $sharedWith,
            )
        );
    }

    private function createDeleteForm($id)
    {
        return $this->createFormBuilder(array('id' => $id))
            ->add('id', 'hidden')
            ->getForm();
    }

    /**
     * To control the User's rights to this question
     *
     */
    private function controlUserQuestion($questionID)
    {
        $user = $this->container->get('security.context')->getToken()->getUser();

        $question = $this->getDoctrine()
            ->getManager()
            ->getRepository('UJMExoBundle:Question')
            ->getControlOwnerQuestion($user->getId(), $questionID);

        return $question;
    }

    /**
     * To paginate table
     *
     */
    private function pagination($entityToPaginate, $max, $page)
    {
        $adapter = new ArrayAdapter($entityToPaginate);
        $pager = new Pagerfanta($adapter);

        try {
            $entityPaginated = $pager
                ->setMaxPerPage($max)
                ->setCurrentPage($page)
                ->getCurrentPageResults();
        } catch (\Pagerfanta\Exception\NotValidCurrentPageException $e) {
            throw $this->createNotFoundException("Cette page n'existe pas.");
        }

        $pagination[0] = $entityPaginated;
        $pagination[1] = $pager;

        return $pagination;
    }

    /**
     * To paginate two tables on one page
     *
     */
    private function doublePaginationWithIf($entityToPaginateOne, $entityToPaginateTwo, $max, $pageOne, $pageTwo, $pageNowOne, $pageNowTwo)
    {
        $adapterOne = new ArrayAdapter($entityToPaginateOne);
        $pagerOne = new Pagerfanta($adapterOne);

        $adapterTwo = new ArrayAdapter($entityToPaginateTwo);
        $pagerTwo = new Pagerfanta($adapterTwo);

        try {
            if ($pageNowOne == 0) {
                $entityPaginatedOne = $pagerOne
                    ->setMaxPerPage($max)
                    ->setCurrentPage($pageOne)
                    ->getCurrentPageResults();
            } else {
                $entityPaginatedOne = $pagerOne
                    ->setMaxPerPage($max)
                    ->setCurrentPage($pageNowOne)
                    ->getCurrentPageResults();
            }

            if ($pageNowTwo == 0) {
                $entityPaginatedTwo = $pagerTwo
                    ->setMaxPerPage($max)
                    ->setCurrentPage($pageTwo)
                    ->getCurrentPageResults();
            } else {
                $entityPaginatedTwo = $pagerTwo
                    ->setMaxPerPage($max)
                    ->setCurrentPage($pageNowTwo
)                    ->getCurrentPageResults();
            }
        } catch (\Pagerfanta\Exception\NotValidCurrentPageException $e) {
            throw $this->createNotFoundException("Cette page n'existe pas.");
        }

        $doublePagination[0] = $entityPaginatedOne;
        $doublePagination[1] = $pagerOne;

        $doublePagination[2] = $entityPaginatedTwo;
        $doublePagination[3] = $pagerTwo;

        return $doublePagination;
    }

    private function getActionInteraction($em, $interaction)
    {
        $response = $em->getRepository('UJMExoBundle:Response')
            ->findBy(array('interaction' => $interaction->getId()));
        if (count($response) > 0) {
            $questionWithResponse[$interaction->getId()] = 1;
        } else {
            $questionWithResponse[$interaction->getId()] = 0;
        }

        $share = $em->getRepository('UJMExoBundle:Share')
            ->findBy(array('question' => $interaction->getQuestion()->getId()));
        if (count($share) > 0) {
            $alreadyShared[$interaction->getQuestion()->getId()] = 1;
        } else {
            $alreadyShared[$interaction->getQuestion()->getId()] = 0;
        }

        $actions[0] = $questionWithResponse;
        $actions[1] = $alreadyShared;

        return $actions;
    }

    private function getActionShared($em, $shared)
    {
        $inter = $em->getRepository('UJMExoBundle:Interaction')
                ->findOneBy(array('question' => $shared->getQuestion()->getId()));

        $sharedWithMe[$shared->getQuestion()->getId()] = $inter;
        $shareRight[$inter->getId()] = $shared->getAllowToModify();

        $response = $em->getRepository('UJMExoBundle:Response')
            ->findBy(array('interaction' => $inter->getId()));

        if (count($response) > 0) {
            $questionWithResponse[$inter->getId()] = 1;
        } else {
            $questionWithResponse[$inter->getId()] = 0;
        }

        $actionsS[0] = $sharedWithMe;
        $actionsS[1] = $shareRight;
        $actionsS[2] = $questionWithResponse;

        return $actionsS;
    }
    
    private function getTypeQCM()
    {
        $typeQCM = array();
        $types = $this->getDoctrine()
                      ->getManager()
                      ->getRepository('UJMExoBundle:TypeQCM')
                      ->findAll();

        foreach ($types as $type) {
            $typeQCM[$type->getId()] = $type->getCode();
        }
        
        return $typeQCM;
    }
    
    private function getTypeOpen()
    {
        $typeOpen = array();
        $types = $this->getDoctrine()
                      ->getManager()
                      ->getRepository('UJMExoBundle:TypeOpenQuestion')
                      ->findAll();

        foreach ($types as $type) {
            $typeOpen[$type->getId()] = $type->getCode();
        }
        
        return $typeOpen;
    }
    
    
    
    
    public function ListQuestionsAction()
    {
            /**             
          $listeQuestions = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:Question')->findAll();
          
                 
           $livres = $this 
            ->getDoctrine() 
            ->getRepository('UJMExoBundle:Question’)->
          
          * 
          * ----
          *   ->where('u.id = :user_id')
                    ->setParameter('user_id', $user->getId())
          
         $listeQuestions = $this->getDoctrine()
                            ->getManager()
                            ->createQueryBuilder()
                            ->select('intqcm')
                            ->from('UJMExoBundle:', 'ch')
                            ->innerJoin('ch.interactionQCM ','intqcm')
                            ->innerJoin('intqcm .interaction ','int')
                            ->innerJoin('int.question ','q')
                            ->getQuery()
                            ->getResult();
             * 
             * 
             * 
          */           
         $id = 3;
         $Question = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:Question')->findBy(array('id' => $id));
         
         /**plusiers interactions */
          $interactions = $this->getDoctrine()
                        ->getManager()
                        ->getRepository('UJMExoBundle:Interaction')->findBy(array('question' => $id));
          
         /**plusieurs interactions qcm*/
          $interactionsqcm = $this->getDoctrine()
                            ->getManager()
                            ->getRepository('UJMExoBundle:InteractionQCM')->findBy(array('interaction' => $interactions[0]->getId()));
          
          $choices2 = $interactionsqcm[0]->getChoices();
          
                    /**
          $choices = $this->getDoctrine()
                            ->getManager()
                            ->getRepository('UJMExoBundle:Choice')->findBy(array('interactionQCM' => $interactionsqcm[0]->getId()));
                       */
          
          
          
            echo "val id ". $Question[0]->getId()."<br>";
            echo "count  ". count($interactions)."<br>";
            echo "count"   .count($interactionsqcm)."<br>";
            echo "count choices2".count($choices2)."<br><br>";
            
            
            
            $var = "<p>Now is the winter of our discontent<br />"
                    . " Made glorious summer by this sun of <input id='1' class='blank' name='blank_1' size='15' type='text' value='york' /> "
                    . ";<br /> And all the clouds that lour'd upon our house<br /> In the deep bosom of the ocean buried.</p>";
            
            $crawler = new Crawler($var);
            
            $src = $crawler->filterXPath('//p')->text();
            //echo htmlentities($src)."<br>";
            
            // $reg="#(<\img+)([^>]*)(>)#";
            $dom = new \DOMDocument();
            
            
            $chaine='<p>Now is the winter of our discontent<br /> Made glorious summer by this sun of <input id="1" class="blank" name="blank_1" size="15" type="text" value="" /> ;<br /> And all the <input id="2" class="blank" name="blank_2" size="15" type="text" value="" /> that lour\'d upon our house<br /> In the deep <input id="3" class="blank" name="blank_3" size="15" type="text" value="" /> of the ocean buried.</p>';
            $txt='<input id="1" class="blank" name="src"  />fgdfgsdfgsdfggf'; 
            $regex = '(<input\\s+id="\d+"\\s+class="blank"\\s+name="blank_\d+"\\s+size="\d+"\\s+type="text"\\s+value=""\\s+\/>)';
            $contenu = preg_replace($regex, "testoooo", $chaine);
            echo htmlentities($contenu);
       
  
                    
  
  //echo preg_replace(".$re1.$re2.$re3.$re4.$re5.$re6.$re7.$re8.$re9.$re10.$re11.$re12.$re13.$re14.$re15.$re16.$re17.$re18.$re19.", '<textEntryInteraction responseIdentifier="RESPONSE" expectedLength="15"/>', $chaine);
 
           
  //echo htmlentities($texte);
            
            
            $dom->loadHTML($chaine);    
            
            
            echo "response before-_-_-_-_-_-_-_-_-_-_-<br>";

                    

                    function nestUl($xml, $xpath)
                    {
                        $dom = new \DOMDocument();
                        $dom->loadXML($xml);

                        $dom_xpath = new \DOMXPath($dom);
                        $nodes = $dom_xpath->query($xpath);

                        foreach($nodes as $node) {
                        $li = $dom->createElement('li');
                        $li->appendChild($node->cloneNode(true));
                        $node->parentNode->replaceChild($li, $node);
                        }
                        return $dom->saveXML();
                    }

                    //$xml = nestUl($xml, 'ul/ul');
                   // $xml = nestUl($xml, '/ul/ul');

                   // echo $xml;
            
            
            
            
            $dom->loadHTML(htmlentities($chaine)); 
            // Clear all errors
            //libxml_clear_errors();

            $xpath = new \DOMXpath($dom);
            

            // Get all child 
            $path = '/p/img';
            $imgs = $xpath->query($path);
            echo $imgs->length."<br>";     
            for($i=0;$i<$imgs->length;$i++) {
                $img = $imgs->item($i);
                echo "img"."<br>";       
                $input_container = $dom->createElement('input');
                $input_container->appendChild($img);
                $dom->replaceChild($input_container, $img);
            }
            $dom->saveHTML();
            //echo htmlentities($chaine)."<br>";    
            
            echo "aaaaaaaaaaaaa-_-_-_-_-_-_-_-_-_-_-<br>";
            
            /*
            $reg="#(?<=\<img)\s*[^>]*(?=>)#";
            $res=preg_replace($reg,"",$chaine); 
            
            $dom = new \DomDocument;
            $dom->loadHTML($res);        
            $imgstags = $dom->getElementsByTagName("img")->item(0);
            $imgstags->setAttribute("src", "path/media/5");
            $dom->saveHTML($res);
            
            echo htmlentities($res);
             //$reg="#(<\w+)([^>]*)(>)#"; 
             * #(?<=\<img)\s*[^>]*(?=>)#    */
            //Code pour eliminer du code html sauf la balise img
             echo htmlentities($chaine)."<br>";
             $res1 =strip_tags($chaine, '<img>');
             echo htmlentities($res1)."<br>";
            //expression regulière pour eliminer tous les attribut des balises         
            $reg="#(?<=\<img)\s*[^>]*(?=>)#";
            $res1=preg_replace($reg,"",$res1);   
                    
            echo htmlentities($res1);
            
            
            /*$interactions = $interqcm->getInteraction();
            echo "count intercations ".count($interactions)."<br>";
            $questions = $interactions->getquestion();
            echo "count questions ".count($questions)."<br>";
            echo "1st question".$questions[0]->get."<br>";*/
            
            
            $Alphabets = array('A','B','C','D','E','F','G','H','I','G','K','L');
            
                $document = new \DOMDocument();      
                // on crée l'élément principal <nouveaute>
		$node = $document->CreateElement('assessmentItem');
                $node->setAttribute("identifier", "choice");
                $node->setAttribute("title",$Question[0]->getTitle());
                $node->setAttribute("adaptive", "false");
                $node->setAttribute("timeDependent", "false");
		$document->appendChild($node);
 
		// on ajoute l'élément <nrnouveaute> a <nouveaute>
		$responseDeclaration = $document->CreateElement('responseDeclaration');
                $responseDeclaration->setAttribute("identifier", "RESPONSE");
                $responseDeclaration->setAttribute("cardinality", "single");
                $responseDeclaration->setAttribute("baseType", "identifier");
                $node->appendChild($responseDeclaration);
                
                    
                $correctResponse = $document->CreateElement('correctResponse');
                $responseDeclaration->appendChild($correctResponse);
                
                    
                
                /**
                <outcomeDeclaration identifier="SCORE" cardinality="single" baseType="float">
                        <defaultValue><value>0</value></defaultValue>
                </outcomeDeclaration>
                **/
                
                $itemBody = $document->CreateElement('itemBody');
                $node->appendChild($itemBody);
                
                $choiceInteraction = $document->CreateElement('choiceInteraction');
                $choiceInteraction->setAttribute("responseIdentifier", "RESPONSE");
                $choiceInteraction->setAttribute("shuffle", "false");
                $choiceInteraction->setAttribute("maxChoices", "1");
                $itemBody->appendChild($choiceInteraction);
                
                $prompt = $document->CreateElement('prompt');
                $choiceInteraction->appendChild($prompt);
                $prompttxt =  $document->CreateTextNode($interactions[0]->getInvite());
		$prompt->appendChild($prompttxt);
                $i=-1;
                foreach($choices2 as $ch){
                    $i++;
                    if($ch->getRightResponse()== true){
                            $value = $document->CreateElement('value');
                            $correctResponse->appendChild($value);
                            $valuetxt =  $document->CreateTextNode("Choice".$Alphabets[$i]);
                            $value->appendChild($valuetxt);
                    }
                    $simpleChoice = $document->CreateElement('simpleChoice');
                    $simpleChoice->setAttribute("identifier", "Choice".$Alphabets[$i]);
                    $choiceInteraction->appendChild($simpleChoice);
                    $simpleChoicetxt =  $document->CreateTextNode($ch->getLabel());
                    $simpleChoice->appendChild($simpleChoicetxt);
                }
                
                    
                    
                
                
                
            //$europe = $dom->getElementsByTagName("europe")->item(0);
            //$europe->appendChild($nouveauPays);
            /**
            $dom->construct();
            $n_selection = $dom->createElement("selection");				
            $n_interprete = $dom->createElement("interprete");							
            $nt_interprete = $dom->createTextNode($nomartiste);	
            $n_interprete->appendChild($nt_interprete);
            $n_selection = $dom->getElementsByTagName("selection")->item(0);
            $n_selection->appendChild($n_interprete);
            $dom->appendChild($n_selection);
             * 
            $url    = "/";
            $html="Testfile.xml";
            $crawler = new Crawler($html, $url);
            */
             echo "<br />";
            echo '=========================change src of img ========================================';
            $document->save('testfile.xml');
            $dom = new \DOMDocument();  
           
            $data = '<img src="q_222855.jpg" alt="" />Quand a été crée Mozila Firefox?';
            $dom->loadHTML($data);
            $listeimgs = $dom->getElementsByTagName("img");
            foreach($listeimgs as $img)
            {
                echo 'find img';
              if ($img->hasAttribute("src")) {
                  echo  " - " . $img->getAttribute("src");
                  $img->setAttribute("src","newvalue");
                  
              }
              echo "<br />";
            }     
            $res = $dom->saveHTML();       
            echo htmlentities($res);
         
           return $this->render(
            'UJMExoBundle:Question:ListQuestions.html.twig', array(
            'Questions' => $Question,
            )
        );
                    
    }
    
    /**
     * Edited by Hamza
     * Export an existing Question.
     *
     */
    public function ExportAction($id,$pageNow)
    {
        $question = $this->controlUserQuestion($id);

        if (count($question) > 0) {

            $interaction = $this->getDoctrine()
                ->getManager()
                ->getRepository('UJMExoBundle:Interaction')
                ->getInteraction($id);

            $typeInter = $interaction[0]->getType();
            
            switch ($typeInter) {
                case "InteractionQCM":
                    
                               $Question = $this->getDoctrine()
                                             ->getManager()
                                             ->getRepository('UJMExoBundle:Question')->findBy(array('id' => $id));

                              /**plusiers interactions */
                               $interactions = $this->getDoctrine()
                                             ->getManager()
                                             ->getRepository('UJMExoBundle:Interaction')->findBy(array('question' => $id));

                              /**plusieurs interactions qcm*/
                               $interactionsqcm = $this->getDoctrine()
                                                 ->getManager()
                                                 ->getRepository('UJMExoBundle:InteractionQCM')->findBy(array('interaction' => $interactions[0]->getId()));

                               $choices2 = $interactionsqcm[0]->getChoices();
                               
                                            // Search for the ID of the ressource from the Invite colonne 
                                               $txt  = $interactions[0]->getInvite();
                                                 //$crawler = new Crawler($txt);          

                                               $path_img="";
                                               $bool = false;
                                               
                                                $dom2 = new \DOMDocument();                  
                                                $dom2->loadHTML(html_entity_decode($txt));
                                                $listeimgs = $dom2->getElementsByTagName("img");
                                                $index = 0;
                                                foreach($listeimgs as $img)
                                                {
                                                  if ($img->hasAttribute("src")) {
                                                     $src= $img->getAttribute("src");
                                                      $id_node= substr($src, 47);
                                                      $resources_file = $this->getDoctrine()
                                                                   ->getManager()
                                                                   ->getRepository('ClarolineCoreBundle:Resource\File')->findBy(array('resourceNode' => $id_node));
                                                       $resources_node = $this->getDoctrine()
                                                                   ->getManager()
                                                                   ->getRepository('ClarolineCoreBundle:Resource\ResourceNode')->findBy(array('id' => $id_node));
                                                       $path_img = $this->container->getParameter('claroline.param.files_directory').'/'.$resources_file[0]->getHashName();
                                                  }
                
                                                }     
                                                //$res_prompt = $dom2->saveHTML();  
                                               
                                               
                                               /*
                                               if ($crawler->filterXPath('//p/img')->count()>0) {
                                                       $bool = true;        
                                                       $src = $crawler->filterXPath('//p/img')->attr('src');
                                                       $id_node= substr($src, 47);
                                                      // echo "qst with img => " . $src."<br>";
                                                      // echo "idd => " . $id_node."<br>";

                                                       $resources_file = $this->getDoctrine()
                                                                   ->getManager()
                                                                   ->getRepository('ClarolineCoreBundle:Resource\File')->findBy(array('resourceNode' => $id_node));
                                                       $resources_node = $this->getDoctrine()
                                                                   ->getManager()
                                                                   ->getRepository('ClarolineCoreBundle:Resource\ResourceNode')->findBy(array('id' => $id_node));
                                                       // echo $ressources_file[0]->getHashName();

                                                       $path_img = $this->container->getParameter('claroline.param.files_directory').'/'.$resources_file[0]->getHashName();

                                               }*/

                               $Alphabets = array('A','B','C','D','E','F','G','H','I','G','K','L');

                               $document = new \DOMDocument();      
                                 // on crée l'élément principal <Node>
                                     $node = $document->CreateElement('assessmentItem');
                                     $node->setAttribute("xmlns", "http://www.imsglobal.org/xsd/imsqti_v2p1");
                                     $node->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
                                     $node->setAttribute("xsi:schemaLocation", "http://www.imsglobal.org/xsd/imsqti_v2p1 http://www.imsglobal.org/xsd/imsqti_v2p1.xsd");
                                     
                                     $node->setAttribute("identifier", "choice");
                                     $node->setAttribute("title",$Question[0]->getTitle());
                                     $node->setAttribute("adaptive", "false");
                                     $node->setAttribute("timeDependent", "false");
                                     $document->appendChild($node);

                                     // Add the tag <responseDeclaration> to <node>
                                     $responseDeclaration = $document->CreateElement('responseDeclaration');
                                     $responseDeclaration->setAttribute("identifier", "RESPONSE");
                                     $responseDeclaration->setAttribute("cardinality", "single");
                                     $responseDeclaration->setAttribute("baseType", "identifier");
                                     $node->appendChild($responseDeclaration);
                                        
                                     
                                     // add the tag <outcomeDeclaration> to the <node>
                                     $outcomeDeclaration = $document->CreateElement('outcomeDeclaration');
                                     $outcomeDeclaration->setAttribute("identifier", "SCORE");
                                     $outcomeDeclaration->setAttribute("cardinality", "single");
                                     $outcomeDeclaration->setAttribute("baseType", "float");
                                     $node->appendChild($outcomeDeclaration);
                                     
                                     
                                     //add the tag <Default value> to the item <outcomeDeclaration>
                                     $defaultValue = $document->CreateElement('defaultValue');
                                     $outcomeDeclaration->appendChild($defaultValue);
                                     $value = $document->CreateElement("value");
                                     $prompttxt =  $document->CreateTextNode("0");
                                     $value->appendChild($prompttxt);
                                     $defaultValue->appendChild($value);
                
                                     

                                     $correctResponse = $document->CreateElement('correctResponse');
                                     $responseDeclaration->appendChild($correctResponse);

                                     $itemBody = $document->CreateElement('itemBody');
                                     $node->appendChild($itemBody);

                                     $choiceInteraction = $document->CreateElement('choiceInteraction');
                                     $choiceInteraction->setAttribute("responseIdentifier", "RESPONSE");
                                     if($interactionsqcm[0]->getShuffle()==1){
                                         $boolval = "true";
                                     }else $boolval = "false";
                                     
                                     $choiceInteraction->setAttribute("shuffle",$boolval);
                                     $choiceInteraction->setAttribute("maxChoices", "1");
                                     $itemBody->appendChild($choiceInteraction);

                                     $prompt = $document->CreateElement('prompt');
                                     $choiceInteraction->appendChild($prompt);
                                        
                                        //Code pour eliminer du code html sauf la balise img
                                        $res1 =strip_tags($interactions[0]->getInvite(), '<img>');
                                        if(!empty($path_img)){
                                            //expression regulière pour eliminer tous les attributs des balises         
                                            $reg="#(?<=\<img)\s*[^>]*(?=>)#";
                                            $res1=preg_replace($reg,"",$res1);   
                                            //rajouter src de l'image
                                            $res1= str_replace("<img>", "<img src=\"".$resources_node[0]->getName()."\" alt=\"\" />",$res1);
                                            //generate the mannifest file
                                            $this->generate_imsmanifest_File($resources_node[0]->getName());
                                        }
                                        
                                            
                                     $prompttxt =  $document->CreateTextNode(html_entity_decode($res1));
                                     $prompt->appendChild($prompttxt);
                                     $i=-1;
                                     foreach($choices2 as $ch){
                                         $i++;
                                         if($ch->getRightResponse()== true){
                                                 $value = $document->CreateElement('value');
                                                 $correctResponse->appendChild($value);
                                                 $valuetxt =  $document->CreateTextNode("Choice".$Alphabets[$i]);
                                                 $value->appendChild($valuetxt);
                                         }
                                         $simpleChoice = $document->CreateElement('simpleChoice');
                                         $simpleChoice->setAttribute("identifier", "Choice".$Alphabets[$i]);
                                         $choiceInteraction->appendChild($simpleChoice);
                                         $simpleChoicetxt =  $document->CreateTextNode(strip_tags($ch->getLabel(),'<img>'));
                                         $simpleChoice->appendChild($simpleChoicetxt);
                                     }
                                 $document->save('testfile.xml');
                                 
                                $file = '/var/www/Claroline/web/testfile.xml';                                 
                
                                

                    
                     
                     //   
                
                                //readfile("/var/www/Claroline/web/testfile.xml");
                    
                
                    /*Debut : Code de telechargement des fichiers    
                    //$hashName = $this->container->get('claroline.utilities.misc')->generateGuid();   
                    $filename = "testfile.xml";           
                    $path = $_SERVER['DOCUMENT_ROOT'] . $this->get('request')->getBasePath() . "/" . $filename;
                    //$content = file_get_contents($path);
                    if (!file_exists($path)) {
                         throw $this->createNotFoundException();
                    }
                     $response = new BinaryFileResponse($path);
                     //$response->headers->set('Content-Type', $content->getContentType());     
                     $response->headers->set('Content-Type', 'application/force-download');
                     $response->headers->set('Content-Disposition', "attachment; filename=$filename");           
                     $response->sendHeaders();             
                     return $response;
                     //Fin : Code de telechargement des fichiers  */ 
              
                    //sfConfig::set('sf_web_debug', false);
                    $tmpFileName = tempnam("/tmp", "xb_");
                    $zip = new \ZipArchive();
                    $zip->open($tmpFileName, \ZipArchive::CREATE);
                    $zip->addFile("/var/www/Claroline/web/testfile.xml", 'ShemaQTI.xml');
                     $zip->addFile("/var/www/Claroline/web/imsmanifest.xml", 'imsmanifest.xml');
                    
                    if(!empty($path_img)){
                         $zip->addFile($path_img, "images/".$resources_node[0]->getName());
                    }
                    $zip->close();
                    $response = new BinaryFileResponse($tmpFileName);                    
                    //$response->headers->set('Content-Type', $content->getContentType());     
                    $response->headers->set('Content-Type', 'application/application/zip');
                    $response->headers->set('Content-Disposition', "attachment; filename=QTI-Archive.zip");           

                
                    return $response;
                 //  return $this->redirect($this->generateUrl('ujm_question_index', array('pageNow' => $pageNow)));
                
                
                case "InteractionGraphic":
                     $Question = $this->getDoctrine()
                                             ->getManager()
                                             ->getRepository('UJMExoBundle:Question')->findBy(array('id' => $id));

                              
                     $interactions = $this->getDoctrine()
                                             ->getManager()
                                             ->getRepository('UJMExoBundle:Interaction')->findBy(array('question' => $id));

                              
                     $interactionGraphic = $this->getDoctrine()
                                                ->getManager()
                                                ->getRepository('UJMExoBundle:InteractionGraphic')->findBy(array('interaction' => $interactions[0]->getId()));
                     
                     $coords = $this->getDoctrine()
                                                ->getManager()
                                                ->getRepository('UJMExoBundle:Coords')->findBy(array('interactionGraphic' => $interactionGraphic[0]->getId()));                           
                     $Documents = $this->getDoctrine()
                                             ->getManager()                                                                    
                                             ->getRepository('UJMExoBundle:Document')->findBy(array('id' => $interactionGraphic[0]->getDocument()));
                     
                     
                /*Claculate Radius  and x,y of the center of the circle
                 * rect: left-x, top-y, right-x, bottom-y.
                 * circle: center-x, center-y, radius. Note. When the radius value is a percentage value,
                 */
                 $Coords_value= $coords[0]->getValue();
                 $Coords_size = $coords[0]->getSize();
                 $radius = $Coords_size/2;
                 list($x, $y) = split('[,]', $Coords_value);
                 
                 $x_center_circle=$x + ($radius);
                 $y_center_circle=$y + ($radius);  
                 
                //creation of the XML FIle      
                     $document = new \DOMDocument(); 
                     
                // on crée l'élément principal <Node>
                    $node = $document->CreateElement('assessmentItem');
                    $node->setAttribute("xmlns", "http://www.imsglobal.org/xsd/imsqti_v2p1");
                    $node->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
                    $node->setAttribute("xsi:schemaLocation", "http://www.imsglobal.org/xsd/imsqti_v2p1 http://www.imsglobal.org/xsd/imsqti_v2p1.xsd"); 
                   
                    $node->setAttribute("identifier", "SelectPoint");
                    $node->setAttribute("title",$Question[0]->getTitle());
                    $node->setAttribute("adaptive", "false");
                    $node->setAttribute("timeDependent", "false");
                    $document->appendChild($node);

                    // Add the tag <responseDeclaration> to <node>
                    $responseDeclaration = $document->CreateElement('responseDeclaration');
                    $responseDeclaration->setAttribute("identifier", "RESPONSE");
                    $responseDeclaration->setAttribute("cardinality", "single");
                    $responseDeclaration->setAttribute("baseType", "point");
                    $node->appendChild($responseDeclaration);

                    // add the tag <correctResponse> to the <responseDeclaration>
                    $correctResponse = $document->createElement("correctResponse");
                    $Tagvalue = $document->CreateElement("value");
                    $responsevalue =  $document->CreateTextNode($x_center_circle." ".$y_center_circle);
                    $Tagvalue->appendChild($responsevalue);
                    $correctResponse->appendChild($Tagvalue);
                    $responseDeclaration->appendChild($correctResponse);
                    
                    
                    //add <areaMapping> to <responseDeclaration>
                    $areaMapping = $document->createElement("areaMapping");
                    $areaMapping->setAttribute("defaultValue", "0");
                    $responseDeclaration->appendChild($areaMapping);
                    
                    $areaMapEntry =  $document->createElement("areaMapEntry");
                    $areaMapEntry->setAttribute("shape", $coords[0]->getShape());
                    $areaMapEntry->setAttribute("coords",$x_center_circle.",".$y_center_circle.",".$radius);
                    $areaMapEntry->setAttribute("mappedValue", "1");
                    $areaMapping->appendChild($areaMapEntry);
                    
                    //add tag <itemBody>... to <assessmentItem>
                    $itemBody =$document->createElement("itemBody");
                    
                    $selectPointInteraction = $document->createElement("selectPointInteraction");
                    $selectPointInteraction->setAttribute("responseIdentifier", "RESPONSE");
                    $selectPointInteraction->setAttribute("maxChoices", "1");
                    
                
                    
                    
                    $prompt = $document->CreateElement('prompt');
                    $prompttxt =  $document->CreateTextNode($interactions[0]->getInvite());
                    $prompt->appendChild($prompttxt);
                    $selectPointInteraction->appendChild($prompt);
                    
                    $object = $document->CreateElement('object');
                    $object->setAttribute("type","image/".$Documents[0]->getType());
                    $object->setAttribute("width",$interactionGraphic[0]->getWidth());
                    $object->setAttribute("height",$interactionGraphic[0]->getHeight());
                    $object->setAttribute("data",$Documents[0]->getUrl());
                    $objecttxt =  $document->CreateTextNode($Documents[0]->getLabel());
                    $object->appendChild($objecttxt);
                    $selectPointInteraction->appendChild($object);
                    
                    
                    $itemBody->appendChild($selectPointInteraction);
                    $node->appendChild($itemBody);
                    //save xml File
                    $document->save('testfile.xml');
                    
                    
                    /*search for the real path with the real name of the image)
                    */
                    $url = substr($Documents[0]->getUrl(), 1, strlen($Documents[0]->getUrl()));
                    $nom = explode("/", $url);
                    
                    //generate tne mannifest file
                    $this->generate_imsmanifest_File($nom[count($nom)-1]);
                    //
                    
                    $path=$_SERVER['DOCUMENT_ROOT'].$this->get('request')->getBasePath(). $url;
                    //create zip file and add the xml file with images...
                    $tmpFileName = tempnam("/tmp", "xb_");
                    $zip = new \ZipArchive();
                    $zip->open($tmpFileName, \ZipArchive::CREATE);
                    $zip->addFile("/var/www/Claroline/web/testfile.xml", 'QTI-Shema.xml');
                    $zip->addFile("/var/www/Claroline/web/imsmanifest.xml", 'imsmanifest.xml');
                    if(!empty($path)){
                            $zip->addFile($path, "images/".$nom[count($nom)-1]);
                    }
                    $zip->close();
                    $response = new BinaryFileResponse($tmpFileName);                    
                    //$response->headers->set('Content-Type', $content->getContentType());     
                    $response->headers->set('Content-Type', 'application/application/zip');
                    $response->headers->set('Content-Disposition', "attachment; filename=QTIarchive.zip");           

                
                    return $response;
                    
                    
                    
                

                case "InteractionHole":
                        $Question = $this->getDoctrine()
                                                 ->getManager()
                                                 ->getRepository('UJMExoBundle:Question')->findBy(array('id' => $id));

                                                 
                         $interactions = $this->getDoctrine()
                                                 ->getManager()
                                                 ->getRepository('UJMExoBundle:Interaction')->findBy(array('question' => $id));


                         $interactionHole = $this->getDoctrine()
                                                    ->getManager()
                                                    ->getRepository('UJMExoBundle:InteractionHole')->findBy(array('interaction' => $interactions[0]->getId()));

                         $ujmHole = $this->getDoctrine()
                                                    ->getManager()
                                                    ->getRepository('UJMExoBundle:Hole')->findBy(array('interactionHole' => $interactionHole[0]->getId()));                           
                         $ujm_word_response = $this->getDoctrine()
                                                 ->getManager()                                                                    
                                                 ->getRepository('UJMExoBundle:WordResponse')->findAll(array('hole' => $ujmHole));
                         
                         //creation of the XML FIle      
                     $document = new \DOMDocument(); 
                     
                   // on crée l'élément principal <Node>
                    $node = $document->CreateElement('assessmentItem');
                    $node->setAttribute("xmlns", "http://www.imsglobal.org/xsd/imsqti_v2p1");
                    $node->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
                    $node->setAttribute("xsi:schemaLocation", "http://www.imsglobal.org/xsd/imsqti_v2p1 http://www.imsglobal.org/xsd/imsqti_v2p1.xsd"); 
                   
                    $node->setAttribute("identifier", "textEntry");
                    $node->setAttribute("title",$Question[0]->getTitle());
                    $node->setAttribute("adaptive", "false");
                    $node->setAttribute("timeDependent", "false");
                    $document->appendChild($node);

                    // Add the tag <responseDeclaration> to <node>
                    $responseDeclaration = $document->CreateElement('responseDeclaration');
                    $responseDeclaration->setAttribute("identifier", "RESPONSE");
                    $responseDeclaration->setAttribute("cardinality", "single");
                    $responseDeclaration->setAttribute("baseType", "string");
                    $node->appendChild($responseDeclaration);

                    
                    
                
                            
                            
                    // add the tag <correctResponse> to the <responseDeclaration>
                    $correctResponse = $document->createElement("correctResponse");
                    
                    foreach($ujm_word_response as $resp){
                       
                        
                        $Tagvalue = $document->CreateElement("value");
                        $responsevalue =  $document->CreateTextNode($resp->getResponse());
                        $Tagvalue->appendChild($responsevalue);
                        $correctResponse->appendChild($Tagvalue);
                        $responseDeclaration->appendChild($correctResponse);
                       
                        
                    }
                    $mapping = $document->createElement("mapping");
                    $mapping->setAttribute("defaultValue", "0");
                    foreach($ujm_word_response as $resp){
                     // add the tag <mapping> to the <responseDeclaration>
                        
                        $mapEntry = $document->createElement("mapEntry");
                        $mapEntry->setAttribute("mapKey", $resp->getResponse());
                        $mapEntry->setAttribute("mappedValue", $resp->getScore());
                        $mapping->appendChild($mapEntry);
                                                                                            
                    }
                    $responseDeclaration->appendChild($mapping);
                    $outcomeDeclaration = $document->createElement("outcomeDeclaration");
                    $outcomeDeclaration->setAttribute("identifier", "SCORE");
                    $outcomeDeclaration->setAttribute("cardinality", "single");
                    $outcomeDeclaration->setAttribute("baseType", "float");
                    $node->appendChild($outcomeDeclaration);
                    
                    //add tag <itemBody>... to <assessmentItem>
                    $itemBody = $document->createElement("itemBody");
                            //change the tag <input....> by <inputentry.....>
                           $qst = $interactionHole[0]->getHtmlWithoutValue();
                           $regex = '(<input\\s+id="\d+"\\s+class="blank"\\s+name="blank_\d+"\\s+size="\d+"\\s+type="text"\\s+value=""\\s+\/>)';
                           $result = preg_replace($regex, '<textEntryInteraction responseIdentifier="RESPONSE" expectedLength="15"/>', $qst);
                    $objecttxt =  $document->CreateTextNode($result);
                    $itemBody->appendChild($objecttxt);
                
                    
              
                    
                    $node->appendChild($itemBody);
                    //save xml File
                    $document->save('Q_Hole.xml');
                    
                
                    //create zip file and add the xml file with images...
                    $tmpFileName = tempnam("/tmp", "xb_");
                    $zip = new \ZipArchive();
                    $zip->open($tmpFileName, \ZipArchive::CREATE);
                    $zip->addFile("/var/www/Claroline/web/Q_Hole.xml", 'QTI-Q-HoleShema.xml');
                
                    $zip->close();
                    $response = new BinaryFileResponse($tmpFileName);                    
                    //$response->headers->set('Content-Type', $content->getContentType());     
                    $response->headers->set('Content-Type', 'application/application/zip');
                    $response->headers->set('Content-Disposition', "attachment; filename=QTI-archive-Q-Hole.zip");           

                
                    return $response;

                case "InteractionOpen":
                    $interactionOpen = $this->getDoctrine()
                    ->getManager()
                    ->getRepository('UJMExoBundle:InteractionOpen')
                    ->getInteractionOpen($interaction[0]->getId());

                   

                    break;
            }
        }
    }
    Public function generate_imsmanifest_File($namefile){
                    $document = new \DOMDocument(); 
                    // on crée l'élément principal <Node>
                    $node = $document->CreateElement('manifest');
                    $node->setAttribute("xmlns", "http://www.imsglobal.org/xsd/imscp_v1p1");
                    $node->setAttribute("xmlns:imsmd", "http://www.imsglobal.org/xsd/imsmd_v1p2");
                    $node->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance"); 
                    $node->setAttribute("xmlns:imsqti", "http://www.imsglobal.org/xsd/imsqti_metadata_v2p1"); 
                    $node->setAttribute("xsi:schemaLocation", "http://www.imsglobal.org/xsd/imscp_v1p1 imscp_v1p1.xsd http://www.imsglobal.org/xsd/imsmd_v1p2 imsmd_v1p2p4.xsd http://www.imsglobal.org/xsd/imsqti_metadata_v2p1  http://www.imsglobal.org/xsd/qti/qtiv2p1/imsqti_metadata_v2p1.xsd"); 

                    $document->appendChild($node);
                    // Add the tag <responseDeclaration> to <node>
                    $metadata = $document->CreateElement('metadata');
                    $node->appendChild($metadata); 
                    
                    $schema = $document->CreateElement('schema');
                    $schematxt = $document->CreateTextNode('IMS Content');
                    $schema->appendChild($schematxt);
                    $metadata->appendChild($schema);
                    
                    
                    $schemaversion=$document->CreateElement('schemaversion');
                    $schemaversiontxt = $document->CreateTextNode('1.1');
                    $schemaversion->appendChild($schemaversiontxt);
                    $metadata->appendChild($schemaversion); 
                    
                    $resources = $document->CreateElement('resources');
                    $node->appendChild($resources);
                    
                    $resource = $document->CreateElement('resource');
                    $resource->setAttribute("type","imsqti_item_xmlv2p1");
                    //the name of the file must be variable ....
                    $resource->setAttribute("href","ShemaQTI.xml");
                    $resources->appendChild($resource);
                    
                    $file = $document->CreateElement('file');
                    $file->setAttribute("href","ShemaQTI.xml");
                    $resource->appendChild($file);
                    
                    $file2 = $document->CreateElement('file');
                    //the name of the image must be variable ....
                    $file2->setAttribute("href","images/".$namefile);
                    $resource->appendChild($file2);
                    
                    $document->save('imsmanifest.xml');
                    
                  
                    
        
    }
    /**
     * 
     * Edited by :Hamza
     * ListQuestions
     *
     */
    public function importAction()
    {
                  $allowedExts = array("xml");
                  $temp = explode(".", $_FILES["f1"]["name"]);
                  $source = $_FILES["f1"]["tmp_name"];
                  $extension = end($temp);
                  $rst= "src tmp_name : ".$source;

                  if ((($_FILES["f1"]["type"] == "text/xml")) && ($_FILES["f1"]["size"] < 20000000) && in_array($extension, $allowedExts)) {


                                if ($_FILES["f1"]["error"] > 0) {
                                  $rst =$rst . "Return Code: " . $_FILES["f1"]["error"] . "<br/>";
                                } else {
                                  $rst =$rst . "File: " . $_FILES["f1"]["name"] . "\n";
                                  $rst =$rst . "Type: " . $_FILES["f1"]["type"] . "\n";
                                  $rst =$rst . "Size: " . ($_FILES["f1"]["size"] / 1024) . " kB\n";
                                  if (file_exists("upload/" . $_FILES["f1"]["name"])) {
                                    $rst =$rst . $_FILES["f1"]["name"] . " already exists. ";
                                  } else {
                                    move_uploaded_file($_FILES["f1"]["tmp_name"],
                                    "/var/www/Claroline/web/uploadfiles/" . $_FILES["f1"]["name"]);
                                    $rst =$rst . "Stored in: " . "uploadfiles/" . $_FILES["f1"]["name"];
                                  }
                                }
                    
                                //import xml file
                                $file = "/var/www/Claroline/web/uploadfiles/".$_FILES["f1"]["name"];
                                $document_xml = new \DomDocument();
                                $document_xml->load($file);
                                $elements = $document_xml->getElementsByTagName('assessmentItem');
                                $element = $elements->item(0); // On obtient le nœud assessmentItem
                                //$childs = $element->childNodes;  
                                if ($element->hasAttribute("title")) {
                                    $title = $element->getAttribute("title");
                                }
                                //get the type of the QCM choiceMultiple or choice
                                $typeqcm = $element->getAttribute("identifier");

                                //Import for Question QCM 
                                if($typeqcm=="choiceMultiple" || $typeqcm=="choice" ){
                                            $nodeList=$element->getElementsByTagName("responseDeclaration");
                                            $responseDeclaration=($nodeList->item(0));
                                            $nodeList2=$responseDeclaration->getElementsByTagName("correctResponse");
                                            $correctResponse=$nodeList2->item(0);
                                            $nodelist3 = $correctResponse->getElementsByTagName("value");

                                            //array correct choices 
                                            $correctchoices = new \Doctrine\Common\Collections\ArrayCollection;

                                            foreach($nodelist3 as $value)  
                                            {
                                                $valeur = $value->nodeValue."\n";
                                                $correctchoices->add($valeur);
                                                //$rst =$rst."--------value : ".$valeur."\n";
                                            }

                                            $nodeList=$element->getElementsByTagName("outcomeDeclaration");
                                            $responseDeclaration=($nodeList->item(0));
                                            $nodeList2=$responseDeclaration->getElementsByTagName("defaultValue");
                                            $correctResponse=$nodeList2->item(0);
                                            $nodelist3 = $correctResponse->getElementsByTagName("value");

                                            foreach($nodelist3 as $score)  
                                            {
                                                $valeur = $score->nodeValue."\n";
                                                $rst =$rst."--------score : ".$valeur."\n";
                                            }

                                            $nodeList=$element->getElementsByTagName("itemBody");
                                            $itemBody=($nodeList->item(0));
                                            $nodeList2=$itemBody->getElementsByTagName("choiceInteraction");
                                            $choiceInteraction=$nodeList2->item(0);
                                            //question
                                            $prompt = $choiceInteraction->getElementsByTagName("prompt")->item(0)->nodeValue;
                                            //$rst =$rst."--------prompt : ".$prompt."\n";



                                            //array correct choices 
                                            $choices = new \Doctrine\Common\Collections\ArrayCollection;
                                            $identifier_choices = new \Doctrine\Common\Collections\ArrayCollection;

                                            $nodeList3=$choiceInteraction->getElementsByTagName("simpleChoice");
                                            foreach($nodeList3 as $simpleChoice)  
                                            {
                                                $choices->add($simpleChoice->nodeValue);
                                                $identifier_choices->add($simpleChoice->getAttribute("identifier"));
                                                //$rst =$rst."--------Choice : ".$valeur."\n";
                                                //$identifier = 
                                                //$rst =$rst."--------identifier ".$identifier."\n";
                                            }


                                            //add the question o the database : 

                                            $question  = new Question();
                                            $Category = new Category();
                                            $interaction =new Interaction();
                                            $interactionqcm =new InteractionQCM();




                                            //question & category
                                            $question->setTitle($title);
                                            //check if the Category "Import" exist --else-- will create it
                                            $Category_import = $this->getDoctrine()
                                                                 ->getManager()
                                                                 ->getRepository('UJMExoBundle:Category')->findBy(array('value' => "import"));
                                            if(count($Category_import)==0){
                                                $Category->setValue("import");
                                                $Category->setUser($this->container->get('security.context')->getToken()->getUser());
                                                $question->setCategory($Category); 
                                            }else{
                                                $question->setCategory($Category_import[0]);
                                            }


                                            $question->setUser($this->container->get('security.context')->getToken()->getUser());
                                            $date = new \Datetime();
                                            $question->setDateCreate(new \Datetime());



                                            //Interaction

                                            $interaction->setType('InteractionQCM');
                                            $interaction->setInvite($prompt);
                                            $interaction->setQuestion($question);







                                            $em = $this->getDoctrine()->getManager();


                                            $ord=1;
                                            $index =0;
                                            foreach ($choices as $choix) {
                                                //choices 
                                                $choice1 = new Choice();
                                                $choice1->setLabel($choix);
                                                $choice1->setOrdre($ord);
                                                foreach ($correctchoices as $corrvalue) {
                                                    $rst= $rst."------------".$identifier_choices[$index]."*--------------------".$corrvalue;
                                                    if(strtolower(trim($identifier_choices[$index])) == strtolower(trim($corrvalue))){
                                                        $rst= $rst."***********".$identifier_choices[$index]."***********".$corrvalue;
                                                        $choice1->setRightResponse(TRUE);
                                                    }
                                                }
                                                $interactionqcm->addChoice($choice1);
                                                $em->persist($choice1);
                                                $ord=$ord+1;
                                                $index=$index+1;
                                            }
                                            //InteractionQCM
                                            $type_qcm = $this->getDoctrine()
                                                        ->getManager()
                                                        ->getRepository('UJMExoBundle:TypeQCM')->findAll();
                                            if($typeqcm=="choice"){
                                                $interactionqcm->setTypeQCM($type_qcm[1]);
                                            }else{
                                                $interactionqcm->setTypeQCM($type_qcm[0]);
                                            }
                                            $interactionqcm->setInteraction($interaction);


                                            $em->persist($interactionqcm);
                                            $em->persist($interactionqcm->getInteraction()->getQuestion());
                                            $em->persist($interactionqcm->getInteraction());

                                            if(count($Category_import)==0){
                                            $em->persist($Category);
                                            }
                                                //echo($choice->getRightResponse());


                                            $em->flush();
                                }  
                            
                  } else {
                    $rst =$rst . "Invalid file";
                  }  
                    $rst = $rst . dirname(__FILE__).'/'."\n"; 
                    
                   //if it's QTI zip file  --> unzip the file into this path "/var/www/Claroline/web/uploadfiles/" --> add to the database the resources (images)       
                  if(($_FILES["f1"]["type"] == "application/zip") && ($_FILES["f1"]["size"] < 20000000)){
                      
                      $rst = 'its a zip file';
                      move_uploaded_file($_FILES["f1"]["tmp_name"],
                                "/var/www/Claroline/web/uploadfiles/" . $_FILES["f1"]["name"]);
                      $zip = new \ZipArchive;
                      $zip->open("/var/www/Claroline/web/uploadfiles/" . $_FILES["f1"]["name"]);
                      $res= zip_open("/var/www/Claroline/web/uploadfiles/" . $_FILES["f1"]["name"]);
                      
                      $zip->extractTo("/var/www/Claroline/web/uploadfiles/" );
                      $tab_liste_fichiers = array();
                      while ($zip_entry = zip_read($res)) //Pour chaque fichier contenu dans le fichier zip 
                        { 
                            if(zip_entry_filesize($zip_entry) > 0) 
                            { 
                                $nom_fichier = zip_entry_name($zip_entry);
                                $rst =$rst . '-_-_-_'.$nom_fichier;
                                array_push($tab_liste_fichiers,$nom_fichier); 

                            }
                        }
                        
                      $zip->close(); 

                      
                            
                        //Import for Question QCM --> from unZip File --> Type choiceMultiple Or  choice
                        //import xml file
                                $file = "/var/www/Claroline/web/uploadfiles/ShemaQTI.xml";
                                $document_xml = new \DomDocument();
                                $document_xml->load($file);
                                $elements = $document_xml->getElementsByTagName('assessmentItem');
                                $element = $elements->item(0); // On obtient le nœud assessmentItem
                                //$childs = $element->childNodes;  
                                if ($element->hasAttribute("title")) {
                                    $title = $element->getAttribute("title");
                                }
                                //get the type of the QCM choiceMultiple or choice
                                $typeqcm = $element->getAttribute("identifier");
                                echo $typeqcm;
                                if(($typeqcm=="choiceMultiple") || ($typeqcm=="choice") ){
                                    
                                                                           //début : récupération des fichiers et stocker les images dans les tables File
                                                                                //creation of the ResourceNode & File for the images...
                                                                                $user= $this->container->get('security.context')->getToken()->getUser();
                                                                                //createur du workspace
                                                                                $workspace = $this->getDoctrine()->getManager()->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->findBy(array('creator' => $user->getId()));
                                                                                //$directory = $this->getReference("directory/{$this->directory}");
                                                                                //$directory = $this->get('claroline.manager.resource_manager');
                                                                                $resourceManager = $this->container->get('claroline.manager.resource_manager');
                                                                                $filesDirectory = $this->container->getParameter('claroline.param.files_directory');
                                                                                $ut = $this->container->get('claroline.utilities.misc');
                                                                                $fileType = $this->getDoctrine()->getManager()->getRepository('ClarolineCoreBundle:Resource\ResourceType')->findOneByName('file');
                                                                                $rst =$rst .'---wrkspace----'.$workspace[0]->getName().'-------------';

                                                                                foreach ($tab_liste_fichiers as $filename) {

                                                                                    //filepath contain the path of the files in the extraction palce "uploadfile"
                                                                                    $filePath = "/var/www/Claroline/web/uploadfiles/".$filename;
                                                                                    $filePathParts = explode(DIRECTORY_SEPARATOR, $filePath);
                                                                                    //file name of the file
                                                                                    $fileName = array_pop($filePathParts);
                                                                                    //extension of the file
                                                                                    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                                                                                    $hashName = "{$ut->generateGuid()}.{$extension}";

                                                                                    $targetFilePath = $filesDirectory . DIRECTORY_SEPARATOR . $hashName;
                                                                                    //$directory = $this->getReference($filesDirectory);

                                                                                    $file = new \Claroline\CoreBundle\Entity\Resource\File();
                                                                                    $file->setName($fileName);
                                                                                    $file->setHashName($hashName);

                                                                                    $rst =$rst . '-_-hashname_-_'.$hashName.'--extention---'.$extension.'--targetFilePath---'.$targetFilePath;    
                                                                                    if(($extension=='jpg')||($extension=='jpeg')||($extension=='gif')){
                                                                                        if (file_exists($filePath)) {
                                                                                            copy($filePath, $targetFilePath);
                                                                                            $file->setSize(filesize($filePath));
                                                                                        } else {
                                                                                            touch($targetFilePath);
                                                                                            $file->setSize(0);
                                                                                        }
                                                                                        $mimeType = MimeTypeGuesser::getInstance()->guess($targetFilePath);
                                                                                        $rst =$rst . '-_-MimeTypeGuesser-_'.$mimeType;
                                                                                        $file->setMimeType($mimeType);

                                                                                        //creation ressourcenode
                                                            //                            $node = new ResourceNode();
                                                            //                            $node->setResourceType($fileType);
                                                            //                            $node->setCreator($user);
                                                            //                            $node->setWorkspace($workspace[0]);
                                                            //                            $node->setCreationDate(new \Datetime());
                                                            //                            $node->setClass('Claroline\CoreBundle\Entity\Resource\File');
                                                            //                            $node->setName($workspace[0]->getName());
                                                            //                            $node->setMimeType($mimeType);

                                                                                       // $file->setResourceNode($node);

                                                                                        //$this->getDoctrine()->getManager()->persist($node);
                                                                                        $role = $this
                                                                                                    ->getDoctrine()
                                                                                                    ->getRepository('ClarolineCoreBundle:Role')
                                                                                                    ->findManagerRole($workspace[0]);
                                                                                        $rigths = array(
                                                                                             'ROLE_WS_MANAGER' => array('open' => true, 'export' => true, 'create' => array(),
                                                                                                                        'role' => $role
                                                                                                                       )
                                                                                        );
                                                                                        //echo 'ws : '.$user->getPersonalWorkspace()->getName();die();
                                                                                        $parent = $this
                                                                                                    ->getDoctrine()
                                                                                                    ->getRepository('ClarolineCoreBundle:Resource\ResourceNode')
                                                                                                    ->findWorkspaceRoot($user->getPersonalWorkspace());

                                                                                        $resourceManager->create($file, $fileType, $user, $user->getPersonalWorkspace(), $parent, NULL, $rigths);// ,$node);
                                                                                            //list of the Resource ID Node that already craeted
                                                                                             $liste_resource_idnode = array();
                                                                                             array_push($liste_resource_idnode,$file->getResourceNode()->getId()); 

                                                                                    }
                                                                                }
                                                                                 //$file->getResourceNode()->getId()  ;die();
                                                                                $this->getDoctrine()->getManager()->flush();  
                                                                      //Fin récupération & stockage 
                                            $nodeList=$element->getElementsByTagName("responseDeclaration");
                                            $responseDeclaration=($nodeList->item(0));
                                            $nodeList2=$responseDeclaration->getElementsByTagName("correctResponse");
                                            $correctResponse=$nodeList2->item(0);
                                            $nodelist3 = $correctResponse->getElementsByTagName("value");

                                            //array correct choices 
                                            $correctchoices = new \Doctrine\Common\Collections\ArrayCollection;

                                            foreach($nodelist3 as $value)  
                                            {
                                                $valeur = $value->nodeValue."\n";
                                                $correctchoices->add($valeur);
                                                //$rst =$rst."--------value : ".$valeur."\n";
                                            }

                                            $nodeList=$element->getElementsByTagName("outcomeDeclaration");
                                            $responseDeclaration=($nodeList->item(0));
                                            $nodeList2=$responseDeclaration->getElementsByTagName("defaultValue");
                                            $correctResponse=$nodeList2->item(0);
                                            $nodelist3 = $correctResponse->getElementsByTagName("value");

                                            foreach($nodelist3 as $score)  
                                            {
                                                $valeur = $score->nodeValue."\n";
                                                $rst =$rst."--------score : ".$valeur."\n";
                                            }

                                            $nodeList=$element->getElementsByTagName("itemBody");
                                            $itemBody=($nodeList->item(0));
                                            $nodeList2=$itemBody->getElementsByTagName("choiceInteraction");
                                            $choiceInteraction=$nodeList2->item(0);
                                            //question
                                            $prompt = $choiceInteraction->getElementsByTagName("prompt")->item(0)->nodeValue;
                                            //change the src of the image :by using this path with integrating the resourceIdNode "/Claroline/web/app_dev.php/file/resource/media/5"
                                                            $dom2 = new \DOMDocument();                  
                                                            $dom2->loadHTML(html_entity_decode($prompt));
                                                            $listeimgs = $dom2->getElementsByTagName("img");
                                                            $index = 0;
                                                            foreach($listeimgs as $img)
                                                            {
                                                              if ($img->hasAttribute("src")) {
                                                                  $img->setAttribute("src","/Claroline/web/app_dev.php/file/resource/media/".$liste_resource_idnode[$index]);
                                                              }
                                                             $index= $index +1;
                                                            }     
                                                            $res_prompt = $dom2->saveHTML();       
                                                           // echo htmlentities($res);
                                            //$rst =$rst."--------prompt : ".$prompt."\n";



                                            //array correct choices 
                                            $choices = new \Doctrine\Common\Collections\ArrayCollection;
                                            $identifier_choices = new \Doctrine\Common\Collections\ArrayCollection;

                                            $nodeList3=$choiceInteraction->getElementsByTagName("simpleChoice");
                                            foreach($nodeList3 as $simpleChoice)  
                                            {
                                                $choices->add($simpleChoice->nodeValue);
                                                $identifier_choices->add($simpleChoice->getAttribute("identifier"));
                                                //$rst =$rst."--------Choice : ".$valeur."\n";
                                                //$identifier = 
                                                //$rst =$rst."--------identifier ".$identifier."\n";
                                            }


                                            //add the question o the database : 

                                            $question  = new Question();
                                            $Category = new Category();
                                            $interaction =new Interaction();
                                            $interactionqcm =new InteractionQCM();




                                            //question & category
                                            $question->setTitle($title);
                                            //check if the Category "Import" exist --else-- will create it
                                            $Category_import = $this->getDoctrine()
                                                                 ->getManager()
                                                                 ->getRepository('UJMExoBundle:Category')->findBy(array('value' => "import"));
                                            
                                            if(count($Category_import)==0){
                                                $Category->setValue("import");
                                                $Category->setUser($this->container->get('security.context')->getToken()->getUser());
                                                $question->setCategory($Category); 
                                            }else{
                                                $question->setCategory($Category_import[0]);
                                            }


                                            $question->setUser($this->container->get('security.context')->getToken()->getUser());
                                            $date = new \Datetime();
                                            $question->setDateCreate(new \Datetime());



                                            //Interaction

                                            $interaction->setType('InteractionQCM');
                                            //strip_tags($res_prompt,'<img><a><p><table>')
                                            $interaction->setInvite(($res_prompt));
                                            $interaction->setQuestion($question);







                                            $em = $this->getDoctrine()->getManager();


                                            $ord=1;
                                            $index =0;
                                            foreach ($choices as $choix) {
                                                //choices 
                                                $choice1 = new Choice();
                                                $choice1->setLabel($choix);
                                                $choice1->setOrdre($ord);
                                                foreach ($correctchoices as $corrvalue) {
                                                    $rst= $rst."------------".$identifier_choices[$index]."*--------------------".$corrvalue;
                                                    if(strtolower(trim($identifier_choices[$index])) == strtolower(trim($corrvalue))){
                                                        $rst= $rst."***********".$identifier_choices[$index]."***********".$corrvalue;
                                                        $choice1->setRightResponse(TRUE);
                                                    }
                                                }
                                                $interactionqcm->addChoice($choice1);
                                                $em->persist($choice1);
                                                $ord=$ord+1;
                                                $index=$index+1;
                                            }
                                            //InteractionQCM
                                            $type_qcm = $this->getDoctrine()
                                                        ->getManager()
                                                        ->getRepository('UJMExoBundle:TypeQCM')->findAll();
                                            if($typeqcm=="choice"){
                                                $interactionqcm->setTypeQCM($type_qcm[1]);
                                            }else{
                                                $interactionqcm->setTypeQCM($type_qcm[0]);
                                            }
                                            $interactionqcm->setInteraction($interaction);


                                            $em->persist($interactionqcm);
                                            $em->persist($interactionqcm->getInteraction()->getQuestion());
                                            $em->persist($interactionqcm->getInteraction());

                                            if(count($Category_import)==0){
                                            $em->persist($Category);
                                            }
                                                //echo($choice->getRightResponse());


                                            $em->flush();
                                }else if($typeqcm=="SelectPoint"){
                                    
                                        $responsedaclr = $element->getElementsByTagName("responseDeclaration");
                                        //$responsedaclr = $elements->item(0);
                                        $nodelist = $responsedaclr->item(0);
                                        $correctresponse = $nodelist->getElementsByTagName("correctResponse");
                
                                        //echo $correctresponse->nodeValue;
                                        //$valeur = $nodelist->getElementByTagName("value"); 
                                        $areaMapping=$responsedaclr->item(0)->getElementsByTagName("areaMapping");
                                        $areaMapEntry =$areaMapping->item(0)->getElementsByTagName("areaMapEntry");
                                        $shape =$areaMapEntry->item(0)->getAttribute("shape");
                                        $coordstxt=$areaMapEntry->item(0)->getAttribute("coords");
                                        $mappedValue=$areaMapEntry->item(0)->getAttribute("mappedValue");
                                        
                                        $itemBody= $element->getElementsByTagName("itemBody");
                                        $selectPointInteraction = $itemBody->item(0)->getElementsByTagName("selectPointInteraction");
                                        $prompt =  $selectPointInteraction->item(0)->getElementsByTagName("prompt");
                                        $object = $selectPointInteraction->item(0)->getElementsByTagName("object");
                                        
                                        
                                        $type=$object->item(0)->getAttribute("type");
                                        $width=$object->item(0)->getAttribute("width");
                                        $height=$object->item(0)->getAttribute("height");
                                        $data=$object->item(0)->getAttribute("data");
                                        
                                        
                                        $question  = new Question();
                                        $Category = new Category();
                                        $interaction =new Interaction();
                                        $interactiongraphic =new InteractionGraphic();
                                        $coords = new Coords();
                                        $ujmdocument = new Document();
                                        
                                        
                                        
                                        $question->setTitle($title);
                                        $Category_import = $this->getDoctrine()
                                                                 ->getManager()
                                                                 ->getRepository('UJMExoBundle:Category')->findBy(array('value' => "import"));
                                            
                                        if(count($Category_import)==0){
                                            $Category->setValue("import");
                                            $Category->setUser($this->container->get('security.context')->getToken()->getUser());
                                            $question->setCategory($Category); 
                                        }else{
                                            $question->setCategory($Category_import[0]);
                                        }
                                        
                                        $question->setUser($this->container->get('security.context')->getToken()->getUser());
                                        $date = new \Datetime();
                                        $question->setDateCreate(new \Datetime());



                                        $interaction->setType('InteractionGraphic');
                                        //strip_tags($res_prompt,'<img><a><p><table>')
                                        $interaction->setInvite(($prompt));
                                        $interaction->setQuestion($question);

                                        $interactiongraphic->setWidth($width);
                                        $interactiongraphic->setHeight($height);
                                        
                                        
                                        //list($x,$y,$z) = split('[,]', $coords);
                                        $parts = explode(",", $coordstxt);
                                        $x = $parts[0];
                                        $y = $parts[1];
                                        $z = $parts[2];
                                        $radius = $z * 2;
                                        $x_center=$x - ($radius);
                                        $y_center=$y - ($radius); 
                                        
                                        $coords->setShape($shape);
                                        $coords->setValue($x_center.",".$y_center);
                                        $coords->setSize($radius);
                                        $coords->setInteractionGraphic($interactiongraphic);
                                        
                                        $user= $this->container->get('security.context')->getToken()->getUser();                                        
                                        $ujmdocument->setUser($user);
                                        $ujmdocument->setLabel($object);
                                            //file name of the file
                                            $listpath = explode("/", $data);  
                                            $fileName =  $listpath[count($listpath)-1];
                                            echo $fileName;
                                            //extension of the file
                                            $extension = pathinfo($data, PATHINFO_EXTENSION);
                                        //il faut changer le nom de l'image
                                        $ujmdocument->setUrl("./uploads/ujmexo/users_documents/".$user->getUsername()."/images/".$fileName);
                                        $ujmdocument->setType($extension);       
                                        
                                        
                                        
                                        $interactiongraphic->setInteraction($interaction);
                                        $interactiongraphic->setDocument($ujmdocument);
                                        
                                              
                                        $em = $this->getDoctrine()->getManager();  
                                          
                                        $em->persist($coords->getInteractionGraphic());
                                        $em->persist($ujmdocument);  
                                        $em->persist($coords);
                                        $em->persist($coords->getInteractionGraphic()->getInteraction()->getQuestion());
                                        $em->persist($coords->getInteractionGraphic()->getInteraction());
                                        
                                        if(count($Category_import)==0){
                                            $em->persist($Category);
                                        }
                                        
                                        $em->flush();
                                        
                                    
                                }
                    
                    
                  }
                  
                  
                  
                            
               
                
                
               /*
                foreach($childs as $enfant) // On prend chaque nœud enfant séparément
                {
                    
                    /*   //$value = $enfant->nodeValue;
                      $nom = $enfant->nodeName; // On prend le nom de chaque nœud
                      $rst =$rst . $nom."<br/>".$value."</br>";
                    if($enfant->hasChildNodes() == true){
                        $childs_level2 = $enfant->childNodes;
                        foreach($childs_level2 as $enfant_l2) // On prend chaque nœud enfant séparément
                        {
                            $enfant_l2->
                            $value = $enfant_l2->nodeValue;
                            $nom = $enfant_l2->nodeName; // On prend le nom de chaque nœud
                            $rst =$rst . $nom."<br/>".$value."</br>";
                        }
                    }
                      
                     
                }  */
                  
                   return $this->render(
                        'UJMExoBundle:Question:ListQuestions.html.twig', array(
                        'rst' => $rst,
                        )
                    );
    }
}
