<?php

namespace UJM\ExoBundle\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * InteractionRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class InteractionRepository extends EntityRepository
{

    /**
     * Get Interaction linked with a question
     *
     * @access public
     *
     * @param integer $questionId id Question
     *
     * Return array[Interaction]
     */
    public function getInteraction($questionId)
    {
        $qb = $this->createQueryBuilder('i');

        $qb->join('i.question', 'q')
            ->where($qb->expr()->in('q.id', $questionId));

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Get Interactions of an user
     *
     * @access public
     *
     * @param integer $uid id User
     *
     * Return array[Interaction]
     */
    public function getUserInteraction($uid)
    {
        $qb = $this->createQueryBuilder('i');

        $qb->join('i.question', 'q')
            ->join('q.category', 'c')
            ->join('q.user', 'u')
            ->where($qb->expr()->in('u.id', $uid))
            ->orderBy('c.value', 'ASC')
            ->addOrderBy('q.title', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get models of an user
     *
     * @access public
     *
     * @param integer $uid id User
     *
     * Return array[Interaction]
     */
    public function getUserModel($uid)
    {
        $qb = $this->createQueryBuilder('i');

        $qb->join('i.question', 'q')
            ->join('q.category', 'c')
            ->join('q.user', 'u')
            ->where($qb->expr()->in('u.id', $uid))
            ->andWhere('q.model in (1)')
            ->orderBy('c.value', 'ASC')
            ->addOrderBy('q.title', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get Interaction of an exercise or for a paper
     *
     * @access public
     *
     * @param Doctrine Entity Manager $em
     * @param intger $exoId id Exercise
     * @param boolean $shuffle if for paper
     * @param integer $nbQuestions if for paper
     *
     * Return array[Interaction]
     */
    public function getExerciseInteraction($em, $exoId, $shuffle, $nbQuestions = 0)
    {
        $interactions = array();
        $questionsList = array();
        $nbQuestionsTot = 0;

        $dql = 'SELECT eq FROM UJM\ExoBundle\Entity\ExerciseQuestion eq WHERE eq.exercise=' . $exoId
            . ' ORDER BY eq.ordre';
        $query = $em->createQuery($dql);
        $eqs = $query->getResult();

        foreach ($eqs as $eq) {
            $questionsList[] = $eq->getQuestion()->getId();
        }

        if ($shuffle == 1) {
            shuffle($questionsList);
        }

        $nbQuestionsTot = count($questionsList);

        if ($nbQuestions > 0) {
            $i = 0;
            $y = 0;
            $newQuestionsList = array();
            while ($i < $nbQuestions) {
                $y = rand(0, $nbQuestionsTot - 1);
                $newQuestionsList[] = $questionsList[$y];
                unset($questionsList[$y]);
                $questionsList = array_merge($questionsList);
                $nbQuestionsTot = count($questionsList);
                $i++;
            }
            $questionsList = array();
            $questionsList = $newQuestionsList;
        }

        foreach ($questionsList as $q) {
            $dql = 'SELECT i FROM UJM\ExoBundle\Entity\Interaction i JOIN i.question q '
                . 'WHERE q=' . $q;
            $query = $em->createQuery($dql);
            $inter = $query->getResult();
            $interactions[] = $inter[0];
        }

        return $interactions;
    }

    /**
     * To import exercise's questions in an other exercise
     *
     * @param Doctrine EntityManager $em
     * @param integer $exoSearch id of exercise selected in the filter
     * @param integer $exoImport id of exercise in which one I want to import questions
     *
     * Return array[Interaction]
     *
     */
    public function getExerciseInteractionImport($em, $exoSearch, $exoImport){
        $questionsList = array();
        $interactions  = array();

        $dql = 'SELECT eq FROM UJM\ExoBundle\Entity\ExerciseQuestion eq
               JOIN eq.question q
               WHERE eq.exercise=' . $exoSearch;
        $dql .= ' AND q.id NOT IN
                (SELECT q2.id FROM UJM\ExoBundle\Entity\ExerciseQuestion eq2
                JOIN eq2.question q2
                JOIN eq2.exercise e2
                WHERE e2.id=' . $exoImport . ')';
        $dql .= ' ORDER BY eq.ordre';

        $query = $em->createQuery($dql);
        $eqs = $query->getResult();

        foreach ($eqs as $eq) {
            $questionsList[] = $eq->getQuestion()->getId();
        }

        foreach ($questionsList as $q) {
            $dql = 'SELECT i FROM UJM\ExoBundle\Entity\Interaction i JOIN i.question q '
                . 'WHERE q=' . $q;
            $query = $em->createQuery($dql);
            $inter = $query->getResult();
            $interactions[] = $inter[0];
        }

        return $interactions;


    }

    /**
     * Interactions linked to the paper
     *
     * @param Doctrine EntityManager $em
     * @param String $ids list of id of Interaction of the paper
     *
     * Return array[Interaction]
     */
    public function getPaperInteraction($em, $ids)
    {
        /* $qb = $this->createQueryBuilder('i');

          $qb ->where($qb->expr()->in('i.id', $ids));

          return $qb->getQuery()->getResult(); */

        $interactions = array();

        $dql = 'SELECT i FROM UJM\ExoBundle\Entity\Interaction i '
            . 'WHERE i.id IN (\'' . $ids . '\') ';
        $query = $em->createQuery($dql);
        $interactions = $query->getResult();

        return $interactions;
    }

    /**
     * To import user's questions in an exercise
     *
     * @param Doctrine EntityManager $em
     * @param integer $uid id of User
     * @param integer $exoId id of exercise
     *
     * Return array[Interaction]
     */
    public function getUserInteractionImport($em, $uid, $exoId)
    {
        /*$dql = "
            SELECT i FROM UJM\ExoBundle\Entity\Interaction i
            JOIN i.question q JOIN q.category c '
            WHERE q.user='{$uid}'
            AND q NOT IN (
                SELECT que FROM UJM\ExoBundle\Entity\ExerciseQuestion eq
                JOIN eq.question que
                WHERE eq.exercise='{$exoId}'
            )
            ORDER BY c.value, q.title";

        return $em->createQuery($dql)->getResult();*/

        $questions = array();

        $dql = 'SELECT eq FROM UJM\ExoBundle\Entity\ExerciseQuestion eq WHERE eq.exercise=' . $exoId
            . ' ORDER BY eq.ordre';

        $query = $em->createQuery($dql);
        $eqs = $query->getResult();

        foreach ($eqs as $eq) {
            $questions[] = $eq->getQuestion()->getId();
        }

        $qb = $this->createQueryBuilder('i');

        $qb->join('i.question', 'q')
           ->join('q.category', 'c')
           ->join('q.user', 'u')
           ->where($qb->expr()->in('u.id', $uid));
        if (count($questions) > 0) {
             $qb->andWhere('q.id not in ('.implode(',', $questions).')');
        }
        $qb->orderBy('c.value', 'ASC')
           ->addOrderBy('q.title', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * To import model's user in an exercise
     *
     * @param Doctrine EntityManager $em
     * @param integer $uid id of User
     * @param integer $exoId id of exercise
     *
     * Return array[Interaction]
     */
    public function getUserModelImport($em, $uid, $exoId)
    {
        $questions = array();

        $dql = 'SELECT eq FROM UJM\ExoBundle\Entity\ExerciseQuestion eq WHERE eq.exercise=' . $exoId
            . ' ORDER BY eq.ordre';

        $query = $em->createQuery($dql);
        $eqs = $query->getResult();

        foreach ($eqs as $eq) {
            $questions[] = $eq->getQuestion()->getId();
        }

        $qb = $this->createQueryBuilder('i');

        $qb->join('i.question', 'q')
           ->join('q.category', 'c')
           ->join('q.user', 'u')
           ->where($qb->expr()->in('u.id', $uid))
           ->andWhere('q.model in (1)');
        if (count($questions) > 0) {
             $qb->andWhere('q.id not in ('.implode(',', $questions).')');
        }
        $qb->orderBy('c.value', 'ASC')
           ->addOrderBy('q.title', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Search interactions of one user by type
     *
     * @param integer $userId id of User
     * @param string $whatToFind the type to find
     *
     * Return array[Interaction]
     */
    public function findByType($userId, $whatToFind)
    {
        $dql = 'SELECT i FROM UJM\ExoBundle\Entity\Interaction i JOIN i.question q
            WHERE i.type LIKE :search
            AND q.user = '.$userId.'
        ';

        $query = $this->_em->createQuery($dql)
            ->setParameter('search', "%{$whatToFind}%");

        return $query->getResult();
    }

    /**
     * Search interactions of one user by contain
     *
     * @param integer $userId id of User
     * @param string $whatToFind the contain to find
     *
     * Return array[Interaction]
     */
    public function findByContain($userId, $whatToFind)
    {
        $dql = 'SELECT i FROM UJM\ExoBundle\Entity\Interaction i JOIN i.question q
            WHERE i.invite LIKE :search
            AND q.user = '.$userId.'
        ';

        $query = $this->_em->createQuery($dql)
            ->setParameter('search', "%{$whatToFind}%");

        return $query->getResult();
    }

    /**
     * Search interactions of one user by all criteria
     *
     * @param integer $userId id of User
     * @param string $whatToFind parameter to find
     * @param boolean $searchToImport if the search is realized from the exercise import and if the interaction can be imported
     * @param integer $exoID id Exercise if user is in an exercise
     *
     * Return array[Interaction]
     */
    public function findByAll($userId, $whatToFind, $searchToImport = FALSE, $exoID = -1)
    {
        $dql = 'SELECT i FROM UJM\ExoBundle\Entity\Interaction i JOIN i.question q JOIN q.category c
            WHERE (i.invite LIKE :search OR i.type LIKE :search OR c.value LIKE :search OR q.title LIKE :search)
            AND q.user = '.$userId.'
        ';

        if ($searchToImport === TRUE) {
            $dql .= ' AND q.id NOT IN
                    (SELECT q2.id FROM UJM\ExoBundle\Entity\ExerciseQuestion eq
                    JOIN eq.question q2
                    JOIN eq.exercise e2
                    WHERE e2.id=' . $exoID . ')';
        }

        $query = $this->_em->createQuery($dql)
            ->setParameter('search', "%{$whatToFind}%");

        return $query->getResult();
    }
}