<?php

namespace PivotLibre\Tideman;

use PHPUnit\Framework\TestCase;

class CandidateListTest extends GenericCollectionTestCase
{
    private const ALICE_ID = "A";
    private const ALICE_NAME = "Alice";
    private const BOB_ID = "B";
    private const BOB_NAME = "Bob";

    protected function setUp()
    {
        $alice = new Candidate(self::ALICE_ID, self::ALICE_NAME);
        $bob = new Candidate(self::BOB_ID, self::BOB_NAME);
        $this->values = array($alice, $bob);
        $this->instance = new CandidateList(...$this->values);
    }

    public function testConstructorTypeSafety() : void
    {
        $variousIllegalArguments = array(
            array(1),
            array(1,2),
            //should fail when passed an array of candidate lists.
            array($this->values)
        );
        foreach ($variousIllegalArguments as $illegalArg) {
            try {
                $instance = new CandidateList($illegalArg);
                //this should never run
                $this->assertEquals(true, false);
            } catch (\TypeError $e) {
                //pass
            }
            try {
                $instance = new CandidateList(...$illegalArg);
                //this should never run
                $this->assertEquals(true, false);
            } catch (\TypeError $e) {
                //pass
            }
        }

        //special case:
        try {
            //should fail when passed an arary of Candidates WITHOUT using ""..."
            $instance = new CandidateList($this->values);
            //this should never run
            $this->assertEquals(true, false);
        } catch (\TypeError $e) {
            //pass
        }

        $instance = new CandidateList(...$this->values);
        $this->assertNotNull($instance);
    }
}
