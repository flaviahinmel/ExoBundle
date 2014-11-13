<?php

/**
 * To export an open (long response) question in QTI
 *
 */

namespace UJM\ExoBundle\Services\classes\QTI;

class openExport extends qtiExport
{
    private $interactionopen;
    private $extendedTetInteraction;

    /**
     * Implements the abstract method
     *
     * @access public
     * @param \UJM\ExoBundle\Entity\Interaction $interaction
     * @param qtiRepository $qtiRepos
     *
     */
    public function export(\UJM\ExoBundle\Entity\Interaction $interaction, qtiRepository $qtiRepos)
    {
        $this->qtiRepos = $qtiRepos;
        $this->question = $interaction->getQuestion();

        $this->interactionopen = $this->doctrine
                                ->getManager()
                                ->getRepository('UJMExoBundle:InteractionOpen')
                                ->findOneBy(array('interaction' => $interaction->getId()));

        $this->qtiHead('extendedText', $this->question->getTitle());
        $this->qtiResponseDeclaration('RESPONSE','string', 'single');
        $this->qtiOutComeDeclaration();
        $this->defaultValueTag();
        $this->itemBodyTag();
        $this->extendedTetInteractionTag();
        $this->promptTag();

        if(($this->interactionopen->getInteraction()->getFeedBack()!=Null)
                && ($this->interactionopen->getInteraction()->getFeedBack()!="") ){
            $this->qtiFeedBack($interaction->getFeedBack());
        }

        $this->document->save($this->qtiRepos->getUserDir().'testfile.xml');

        return $this->getResponse();
    }

    /**
     * add the tag extendedTetInteraction in itemBody
     *
     * @access private
     *
     */
    private function extendedTetInteractionTag()
    {
        $this->extendedTetInteraction = $this->document->CreateElement('extendedTetInteraction');
        $this->extendedTetInteraction->setAttribute("responseIdentifier", "RESPONSE");
        $this->itemBody->appendChild($this->extendedTetInteraction);
    }

    /**
     * Implements the abstract method
     * add the tag prompt in extendedTetInteraction
     *
     * @access protected
     *
     */
    protected function promptTag()
    {
        $prompt = $this->document->CreateElement('prompt');
        $prompttxt = $this->document->CreateTextNode($this->interactionopen->getInteraction()->getInvite());
        $prompt->appendChild($prompttxt);
        $this->extendedTetInteraction->appendChild($prompt);
    }


    /**
     * Implements the abstract method
     * add the tag correctResponse in responseDeclaration
     *
     * @access protected
     *
     */
    protected function correctResponseTag()
    {
        $this->correctResponse = $this->document->CreateElement('correctResponse');
        $this->responseDeclaration[0]->appendChild($this->correctResponse);
    }

    /**
     * add the tag defaultValue in outcomeDeclaration
     *
     * @access private
     *
     */
    private function defaultValueTag()
    {
        $defaultValue = $this->document->createElement("defaultValue");
        $Tagvalue = $this->document->CreateElement("value");
        $responsevalue =  $this->document->CreateTextNode($this->interactionopen->getScoreMaxLongResp());
        $Tagvalue->appendChild($responsevalue);
        $defaultValue->appendChild($Tagvalue);
        $this->outcomeDeclaration->appendChild($defaultValue);
    }
}