<?php
namespace PivotLibre\Tideman;

use PivotLibre\Tideman\MarginRegistry;
use \InvalidArgumentException;

class MarginCalculator
{
    public function __construct()
    {
        /**
         * @todo #7 Decide whether the NBallots should be passed to the calculator
         * at instantiation, or when invoking calculate().
         */
    }


    /**
     * Register a Margin for all possible pairs of Candidates described in an Agenda. If the agenda contains N
     * Candidates, then this method should register (N^2) - N = N(N - 1) Candidates.
     *
     * @todo #7 Decide whether this method should be a part of the MarginCalculator class or the Agenda class.
     */
    public function initializeRegistry(Agenda $agenda) : MarginRegistry
    {
        $registry = new MarginRegistry();
        foreach ($agenda->getCandidates() as $outerCandidate) {
            foreach ($agenda->getCandidates() as $innerCandidate) {
                /**
                 * It only makes sense to keep track of the difference of public support between
                 * two DIFFERENT candidates. It doesn't make sense to keep track of the difference between
                 * public support of a candidate and themself.
                 */
                if ($outerCandidate->getId() != $innerCandidate->getId()) {
                    $margin = new Margin($outerCandidate, $innerCandidate, 0);
                    $registry->register($margin);
                }
            }
        }
        return $registry;
    }
    /**
     * Return an associative array that maps Candidates' ids to an integer. The integer represents the rank of the
     * Candidate within the Ballot. A lower index indicates higher preference. An index of zero is the highest rank.
     * Since a Ballot can contain ties, multiple Candidate ids can map to the same integer rank.
     */
    public function getCandidateIdToRankMap(Ballot $ballot) : array
    {
        $candidateIdToRank = array();
        foreach ($ballot as $rank => $candidateList) {
            foreach ($candidateList as $candidate) {
                $candidateId = $candidate->getId();
                if (isset($candidateIdToRank[$candidateId])) {
                    throw new InvalidArgumentException(
                        "A Ballot cannot contain a candidate more than once."
                        . " Offending Ballot: " . var_export($ballot, true)
                        . " Offending Candidate: " . var_export($candidate, true)
                    );
                } else {
                    $candidateIdToRank[$candidateId] = $rank;
                }
            }
        }
        return $candidateIdToRank;
    }

    /**
     * @return array whose zeroth element is the winning Candidate, and whose
     * first element is the losing Candidate. It is suggested that the results of this function be assigned via list().
     * Example:
     * list($winner, $loser) = getWinnerAndLoser(...);
     */
    public function getWinnerAndLoser(
        Candidate $outerCandidate,
        Candidate $innerCandidate,
        array $candidateIdToRank
    ) : array {
        $outerCandidateId = $outerCandidate->getId();
        $innerCandidateId = $innerCandidate->getId();
        if (!isset($candidateIdToRank[$outerCandidateId])) {
            throw new InvalidArgumentException(
                "Candidate's Id should be in the map of ID to Rank.\n"
                . " Candidate: " . $outerCandidate . "\n"
                . " Mapping: " . var_export($candidateIdToRank, true)
            );
        } elseif (!isset($candidateIdToRank[$innerCandidateId])) {
            throw new InvalidArgumentException(
                "Candidate's Id should be in the map of ID to Rank.\n"
                . " Candidate: " . $innerCandidate . "\n"
                . " Mapping: " . var_export($candidateIdToRank, true)
            );
        } else {
            $outerCandidateRank = $candidateIdToRank[$outerCandidateId];
            $innerCandidateRank = $candidateIdToRank[$innerCandidateId];

            //the candidate with the lower rank is the winner
            if ($outerCandidateRank < $innerCandidateRank) {
                $winnerAndLoser = array($outerCandidate, $innerCandidate);
            } else {
                /**
                 * no need to explicitly handle special case $outerCandidateRank == $innerCandidateRank
                 * One margin for the tied pair will be populated on the first iteration through candidates.
                 * The other margin will be populated on the the second iteration through candidates. At that point,
                 * the candidate arguments will be transposed.
                 */
                $winnerAndLoser = array($innerCandidate, $outerCandidate);
            }
            return $winnerAndLoser;
        }
    }
    /**
     *
     * Adds the $amountToAdd to the count already associated with the appropriate Margin in the
     * MarginRegistry.
     *
     * @param Candidate $winner
     * @param Candidate $loser
     * @param MarginRegistry $registry
     * @param int $amountToAdd
     */
    public function incrementMarginInRegistry(
        Candidate $winner,
        Candidate $loser,
        MarginRegistry $registry,
        int $amountToAdd
    ) : void {
        $marginToUpdate = $registry->get($winner, $loser);
        $updatedMargin = $marginToUpdate->getMargin() + $amountToAdd;
        $marginToUpdate->setMargin($updatedMargin);
    }
    /**
     * @return a MarginRegistry whose Margins completely describe the pairwise
     * difference in popular support between every Candidate.
     */
    public function calculate(Agenda $agenda, NBallot ...$nBallots) : MarginRegistry
    {
        $registry = $this->initializeRegistry($agenda);

        foreach ($nBallots as $nBallot) {
            $this->tallyNBallot($nBallot, $registry, $agenda);
        }
        return $registry;
    }
    public function tallyNBallot(NBallot $nBallot, MarginRegistry $registry, Agenda $agenda) : void
    {
        //a map of candidate id to their integer rank
        $candidateIdToRank = $this->getCandidateIdToRankMap($nBallot);
        $ballotCount = $nBallot->getCount();
        $orderedPairs = $this->getOrderedPairs($agenda);
        foreach ($orderedPairs as $pair) {
            list($outerCandidate, $innerCandidate) = $pair;
            list($winner, $loser) = $this->getWinnerAndLoser(
                $outerCandidate,
                $innerCandidate,
                $candidateIdToRank
            );
            $this->incrementMarginInRegistry(
                $winner,
                $loser,
                $registry,
                $ballotCount
            );
        }
    }
    /**
     * Returns all ordered pairs of Candidates in the election.
     * @param Agenda $agenda the set of Candidates relevant to the election
     * @return array of arrays. Each inner array contains two Candidates.
     * The outer array is of length N(N-1) because it omits pairs containing two of the same Candidates.
     */
    public function getOrderedPairs(Agenda $agenda) : array
    {
        $pairs = array();
        foreach ($agenda->getCandidates() as $outerCandidate) {
            foreach ($agenda->getCandidates() as $innerCandidate) {
                if ($outerCandidate->getId() != $innerCandidate->getId()) {
                    $pair = array($outerCandidate, $innerCandidate);
                    $pairs[] = $pair;
                }
            }
        }
        return $pairs;
    }
}
