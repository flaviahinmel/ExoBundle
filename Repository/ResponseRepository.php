<?php

namespace UJM\ExoBundle\Repository;

use Doctrine\ORM\EntityRepository;

/**
 * ResponseRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ResponseRepository extends EntityRepository
{
    /**
     * Allow to know if exists already a response for a question of a user's paper
     *
     * @access public
     *
     * @param integer $paperID id Paper
     * @param integer $interactionID id Interaction
     *
     * Return array[Response]
     */
    public function getAlreadyResponded($paperID, $interactionID)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->join('r.paper', 'p')
            ->join('r.interaction', 'i')
            ->where($qb->expr()->in('p.id', $paperID))
            ->andWhere($qb->expr()->in('i.id', $interactionID));

        return $qb->getQuery()->getResult();
    }

    /**
     * Get the reponses for a paper and an user
     *
     * @access public
     *
     * @param integer $uid id User
     * @param integer $paperID id paper
     *
     * Return array[Response]
     */
    public function getPaperResponses($uid, $paperID)
    {
        $qb = $this->createQueryBuilder('r');
        $qb->join('r.paper', 'p')
            ->join('p.user', 'u')
            ->where($qb->expr()->in('p.id', $paperID))
            ->andWhere($qb->expr()->in('u.id', $uid));

        return $qb->getQuery()->getResult();
    }

    /**
     * Get the score for an exercise and an interaction with count
     *
     * @access public
     *
     * @param integer $exoId id Exercise
     * @param integer $interId id Interaction
     *
     * Return array[Response]
     */
    public function getExerciseInterResponsesWithCount($exoId, $interId)
    {
        $dql = 'SELECT r.mark, count(r.mark) as nb
            FROM UJM\ExoBundle\Entity\Response r, UJM\ExoBundle\Entity\Interaction i, UJM\ExoBundle\Entity\Question q, UJM\ExoBundle\Entity\Paper p
            WHERE r.interaction=i.id AND i.question=q.id AND r.paper=p.id AND p.exercise='.$exoId.' AND r.interaction ='.$interId.' AND r.response != \'\' GROUP BY r.mark';

        $query = $this->_em->createQuery($dql);

        return $query->getResult();
    }

    /**
     * Send the score for an exercise and an interaction
     *
     * @access public
     *
     * @param integer $exoId id Exercise
     * @param integer $interId id Interaction
     *
     * Return array[Response]
     */
    public function getExerciseInterResponses($exoId, $interId)
    {
        $dql = 'SELECT r.mark
            FROM UJM\ExoBundle\Entity\Response r, UJM\ExoBundle\Entity\Interaction i, UJM\ExoBundle\Entity\Question q, UJM\ExoBundle\Entity\Paper p
            WHERE r.interaction=i.id AND i.question=q.id AND r.paper=p.id AND p.exercise='.$exoId.' AND r.interaction ='.$interId.' ORDER BY p.id';

        $query = $this->_em->createQuery($dql);

        return $query->getResult();
    }
}