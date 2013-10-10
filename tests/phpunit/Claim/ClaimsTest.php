<?php

namespace Wikibase\Test;

use DataValues\StringValue;
use Diff\Diff;
use Diff\DiffOpAdd;
use Diff\DiffOpChange;
use Diff\DiffOpRemove;
use ReflectionClass;
use Wikibase\Claim;
use Wikibase\Claims;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Property;
use Wikibase\PropertyNoValueSnak;
use Wikibase\PropertySomeValueSnak;
use Wikibase\PropertyValueSnak;
use Wikibase\Reference;
use Wikibase\ReferenceList;
use Wikibase\Snak;
use Wikibase\SnakList;
use Wikibase\Statement;

/**
 * @covers Wikibase\Claims
 *
 * @since 0.1
 *
 * @group Wikibase
 * @group WikibaseDataModel
 * @group WikibaseClaim
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class ClaimsTest extends \PHPUnit_Framework_TestCase {

	protected $guidCounter = 0;

	protected function makeClaim( Snak $mainSnak, $guid = null ) {
		if ( $guid === null ) {
			$this->guidCounter++;
			$guid = 'TEST$claim-' . $this->guidCounter;
		}

		$claim = new Claim( $mainSnak );
		$claim->setGuid( $guid );

		return $claim;
	}

	protected function makeStatement( Snak $mainSnak, $guid = null ) {
		if ( $guid === null ) {
			$this->guidCounter++;
			$guid = 'TEST$statement-' . $this->guidCounter;
		}

		$claim = new Statement( $mainSnak );
		$claim->setGuid( $guid );

		return $claim;
	}

	/**
	 * @dataProvider constructorProvider
	 */
	public function testConstructor() {
		$class = new ReflectionClass( 'Wikibase\Claims' );
		$class->newInstanceArgs( func_get_args() );
	}

	public function constructorProvider() {
		return array(
			array(),
			array( null ),
			array( array() ),
			array( array( $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) ) ) ),
		);
	}

	/**
	 * @dataProvider constructorErrorProvider
	 */
	public function testConstructorError() {
		$this->setExpectedException( 'InvalidArgumentException' );

		$class = new ReflectionClass( 'Wikibase\Claims' );
		$class->newInstanceArgs( func_get_args() );
	}

	public function constructorErrorProvider() {
		return array(
			array( 17 ),
			array( array( "foo" ) ),
		);
	}

	public function testHasClaim() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$this->assertFalse( $claims->hasClaim( $claim1 ) );
		$this->assertFalse( $claims->hasClaim( $claim2 ) );

		$claims->addClaim( $claim1 );
		$this->assertTrue( $claims->hasClaim( $claim1 ) );
		$this->assertFalse( $claims->hasClaim( $claim2 ) );

		$claims->addClaim( $claim2 );
		$this->assertTrue( $claims->hasClaim( $claim1 ) );
		$this->assertTrue( $claims->hasClaim( $claim2 ) );

		// no guid
		$claim0 = new Claim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$this->assertFalse( $claims->hasClaim( $claim0 ) );
	}

	public function testHasClaimWithGuid() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$this->assertFalse( $claims->hasClaimWithGuid( $claim1->getGuid() ) );
		$this->assertFalse( $claims->hasClaimWithGuid( $claim2->getGuid() ) );

		$claims->addClaim( $claim1 );
		$this->assertTrue( $claims->hasClaimWithGuid( $claim1->getGuid() ) );
		$this->assertFalse( $claims->hasClaimWithGuid( $claim2->getGuid() ) );

		$claims->addClaim( $claim2 );
		$this->assertTrue( $claims->hasClaimWithGuid( $claim1->getGuid() ) );
		$this->assertTrue( $claims->hasClaimWithGuid( $claim2->getGuid() ) );
	}

	public function testRemoveClaim() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$claims->addClaim( $claim1 );
		$claims->addClaim( $claim2 );
		$this->assertCount( 2, $claims );

		$claims->removeClaim( $claim1 );
		$this->assertFalse( $claims->hasClaim( $claim1 ) );
		$this->assertNull( $claims->getClaimWithGuid( $claim1->getGuid() ) );
		$this->assertCount( 1, $claims );

		$claims->removeClaim( $claim2 );
		$this->assertFalse( $claims->hasClaim( $claim2 ) );
		$this->assertNull( $claims->getClaimWithGuid( $claim2->getGuid() ) );
		$this->assertCount( 0, $claims );

		// no guid
		$claim0 = new Claim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claims->removeClaim( $claim0 );
	}

	public function testRemoveClaimWithGuid() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$claims->addClaim( $claim1 );
		$claims->addClaim( $claim2 );
		$this->assertCount( 2, $claims );

		$claims->removeClaimWithGuid( $claim1->getGuid() );
		$this->assertFalse( $claims->hasClaim( $claim1 ) );
		$this->assertNull( $claims->getClaimWithGuid( $claim1->getGuid() ) );
		$this->assertCount( 1, $claims );

		$claims->removeClaimWithGuid( $claim2->getGuid() );
		$this->assertFalse( $claims->hasClaim( $claim2 ) );
		$this->assertNull( $claims->getClaimWithGuid( $claim2->getGuid() ) );
		$this->assertCount( 0, $claims );
	}

	public function testOffsetUnset() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$claims->addClaim( $claim1 );
		$claims->addClaim( $claim2 );
		$this->assertCount( 2, $claims );

		$claims->offsetUnset( $claim1->getGuid() );
		$this->assertFalse( $claims->hasClaim( $claim1 ) );
		$this->assertNull( $claims->getClaimWithGuid( $claim1->getGuid() ) );
		$this->assertCount( 1, $claims );

		$claims->offsetUnset( $claim2->getGuid() );
		$this->assertFalse( $claims->hasClaim( $claim2 ) );
		$this->assertNull( $claims->getClaimWithGuid( $claim2->getGuid() ) );
		$this->assertCount( 0, $claims );
	}

	public function testGetClaimWithGuid() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$claims->addClaim( $claim1 );
		$this->assertSame( $claim1, $claims->getClaimWithGuid( $claim1->getGuid() ) );
		$this->assertNull( $claims->getClaimWithGuid( $claim2->getGuid() ) );

		$claims->addClaim( $claim2 );
		$this->assertSame( $claim1, $claims->getClaimWithGuid( $claim1->getGuid() ) );
		$this->assertSame( $claim2, $claims->getClaimWithGuid( $claim2->getGuid() ) );
	}

	public function testOffsetGet() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$claims->addClaim( $claim1 );
		$this->assertSame( $claim1, $claims->offsetGet( $claim1->getGuid() ) );

		$claims->addClaim( $claim2 );
		$this->assertSame( $claim1, $claims->offsetGet( $claim1->getGuid() ) );
		$this->assertSame( $claim2, $claims->offsetGet( $claim2->getGuid() ) );
	}

	public function testAddClaim() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$claims->addClaim( $claim1 );
		$claims->addClaim( $claim2 );

		$this->assertCount( 2, $claims );
		$this->assertEquals( $claim1, $claims[$claim1->getGuid()] );
		$this->assertEquals( $claim2, $claims[$claim2->getGuid()] );

		$claims->addClaim( $claim1 );
		$claims->addClaim( $claim2 );

		$this->assertCount( 2, $claims );

		$this->assertNotNull( $claims->getClaimWithGuid( $claim1->getGuid() ) );
		$this->assertNotNull( $claims->getClaimWithGuid( $claim2->getGuid() ) );
	}

	public function testAppend() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$claims->append( $claim1 );
		$claims->append( $claim2 );

		$this->assertCount( 2, $claims );
		$this->assertEquals( $claim1, $claims[$claim1->getGuid()] );
		$this->assertEquals( $claim2, $claims[$claim2->getGuid()] );

		$claims->append( $claim1 );
		$claims->append( $claim2 );

		$this->assertCount( 2, $claims );
	}

	public function testAppendOperator() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$claims[] = $claim1;
		$claims[] = $claim2;

		$this->assertCount( 2, $claims );
		$this->assertEquals( $claim1, $claims[$claim1->getGuid()] );
		$this->assertEquals( $claim2, $claims[$claim2->getGuid()] );

		$claims[] = $claim1;
		$claims[] = $claim2;

		$this->assertCount( 2, $claims );
	}

	public function testOffsetSet() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$claims->offsetSet( $claim1->getGuid(), $claim1 );
		$claims->offsetSet( $claim2->getGuid(), $claim2 );

		$this->assertCount( 2, $claims );
		$this->assertEquals( $claim1, $claims[$claim1->getGuid()] );
		$this->assertEquals( $claim2, $claims[$claim2->getGuid()] );

		$claims->offsetSet( $claim1->getGuid(), $claim1 );
		$claims->offsetSet( $claim2->getGuid(), $claim2 );

		$this->assertCount( 2, $claims );

		$this->setExpectedException( 'InvalidArgumentException' );
		$claims->offsetSet( 'spam', $claim1 );
	}

	public function testOffsetSetOperator() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$claims[$claim1->getGuid()] = $claim1;
		$claims[$claim2->getGuid()] = $claim2;

		$this->assertCount( 2, $claims );
		$this->assertEquals( $claim1, $claims[$claim1->getGuid()] );
		$this->assertEquals( $claim2, $claims[$claim2->getGuid()] );

		$claims[$claim1->getGuid()] = $claim1;
		$claims[$claim2->getGuid()] = $claim2;

		$this->assertCount( 2, $claims );
	}

	public function testGuidNormalization() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$claim1LowerGuid = strtolower( $claim1->getGuid() );
		$claim2UpperGuid = strtoupper( $claim2->getGuid() );

		$claims->addClaim( $claim1 );
		$claims->addClaim( $claim2 );
		$this->assertCount( 2, $claims );

		$this->assertEquals( $claim1, $claims->getClaimWithGuid( $claim1LowerGuid ) );
		$this->assertEquals( $claim2, $claims->getClaimWithGuid( $claim2UpperGuid ) );

		$this->assertEquals( $claim1, $claims->offsetGet( $claim1LowerGuid ) );
		$this->assertEquals( $claim2, $claims->offsetGet( $claim2UpperGuid ) );

		$this->assertEquals( $claim1, $claims[$claim1LowerGuid] );
		$this->assertEquals( $claim2, $claims[$claim2UpperGuid] );

		$claims = new Claims();
		$claims->offsetSet( strtoupper( $claim1LowerGuid ), $claim1 );
		$claims->offsetSet( strtolower( $claim2UpperGuid ), $claim2 );
		$this->assertCount( 2, $claims );

		$this->assertEquals( $claim1, $claims->getClaimWithGuid( $claim1LowerGuid ) );
		$this->assertEquals( $claim2, $claims->getClaimWithGuid( $claim2UpperGuid ) );
	}

	public function testGetMainSnaks() {
		$claims = new Claims( array(
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P42" ) ) ),
			$this->makeClaim( new PropertySomeValueSnak( new PropertyId( "P42" ) ) ),
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P23" ) ) ),
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P9000" ) ) ),
		) );

		$snaks = $claims->getMainSnaks();
		$this->assertInternalType( 'array', $snaks );
		$this->assertSameSize( $claims, $snaks );

		foreach ( $snaks as $guid => $snak ) {
			$this->assertInstanceOf( 'Wikibase\Snak', $snak );
			$this->assertTrue( $claims->hasClaimWithGuid( $guid ) );
		}
	}

	public function testGetGuids() {
		$claims = new Claims( array(
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P42" ) ) ),
			$this->makeClaim( new PropertySomeValueSnak( new PropertyId( "P42" ) ) ),
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P23" ) ) ),
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P9000" ) ) ),
		) );

		$guids = $claims->getGuids();
		$this->assertInternalType( 'array', $guids );
		$this->assertSameSize( $claims, $guids );

		foreach ( $guids as $guid ) {
			$this->assertInternalType( 'string', $guid );
			$this->assertTrue( $claims->hasClaimWithGuid( $guid ) );
		}
	}

	public function testGetHashes() {
		$claims = new Claims( array(
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P42" ) ) ),
			$this->makeClaim( new PropertySomeValueSnak( new PropertyId( "P42" ) ) ),
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P23" ) ) ),
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P9000" ) ) ),
		) );

		$hashes = $claims->getHashes();
		$this->assertInternalType( 'array', $hashes );
		$this->assertSameSize( $claims, $hashes );

		foreach ( $hashes as $hash ) {
			$this->assertInternalType( 'string', $hash );
		}

		$hashSet = array_flip( $hashes );

		foreach ( $claims as $claim ) {
			$hash = $claim->getHash();
			$this->assertArrayHasKey( $hash, $hashSet );
		}
	}

	public function testGetClaimsForProperty() {
		$claims = new Claims( array(
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P42" ) ) ),
			$this->makeClaim( new PropertySomeValueSnak( new PropertyId( "P42" ) ) ),
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P23" ) ) ),
		) );

		$matches = $claims->getClaimsForProperty( 42 );
		$this->assertInstanceOf( 'Wikibase\Claims', $claims );
		$this->assertCount( 2, $matches );

		$matches = $claims->getClaimsForProperty( 23 );
		$this->assertInstanceOf( 'Wikibase\Claims', $claims );
		$this->assertCount( 1, $matches );

		$matches = $claims->getClaimsForProperty( 9000 );
		$this->assertInstanceOf( 'Wikibase\Claims', $claims );
		$this->assertCount( 0, $matches );
	}

	/**
	 * Attempts to add Claims with no GUID set will fail.
	 */
	public function testNoGuidFailure() {
		$claim = new Claim( new PropertyNoValueSnak( 42 ) );
		$list = new Claims();

		$this->setExpectedException( 'InvalidArgumentException' );
		$list->addClaim( $claim );
	}

	public function testDuplicateClaims() {
		$firstClaim = $this->makeClaim( new PropertyNoValueSnak( 42 ) );
		$secondClaim = $this->makeClaim( new PropertyNoValueSnak( 42 ) );

		$list = new Claims();
		$list->addClaim( $firstClaim );
		$list->addClaim( $secondClaim );

		$this->assertEquals( 2, count( $list ), 'Adding two duplicates to an empty list should result in a count of two' );

		$this->assertEquals( $firstClaim, $list->getClaimWithGuid( $firstClaim->getGuid() ) );
		$this->assertEquals( $secondClaim, $list->getClaimWithGuid( $secondClaim->getGuid() ) );

		$list->removeClaimWithGuid( $secondClaim->getGuid() );

		$this->assertNotNull( $list->getClaimWithGuid( $firstClaim->getGuid() ) );
		$this->assertNull( $list->getClaimWithGuid( $secondClaim->getGuid() ) );
	}

	public function getDiffProvider() {
		$argLists = array();

		$claim0 = $this->makeClaim( new PropertyNoValueSnak( 42 ) );
		$claim1 = $this->makeClaim( new PropertySomeValueSnak( 42 ) );
		$claim2 = $this->makeClaim( new PropertyValueSnak( 42, new StringValue( 'ohi' ) ) );
		$claim3 = $this->makeClaim( new PropertyNoValueSnak( 1 ) );
		$claim4 = $this->makeClaim( new PropertyNoValueSnak( 2 ) );

		$statement0 = $this->makeStatement( new PropertyNoValueSnak( 5 ) );
		$statement0->setRank( Statement::RANK_PREFERRED );

		$statement1 = $this->makeStatement( new PropertyNoValueSnak( 5 ) );
		$statement1->setReferences( new ReferenceList( array( new Reference(
			new SnakList( array( new PropertyValueSnak( 10, new StringValue( 'spam' ) ) ) )
		) ) ) );

		// same GUID, changed main snak
		$claim2v2 = unserialize( serialize( $claim2 ) );
		$claim2v2->setMainSnak( new PropertyValueSnak( 42, new StringValue( 'omnomnom' ) ) );

		// different GUID, same contents, same hash
		$claim0a = unserialize( serialize( $claim0 ) );
		$claim0a->setGuid( 'TEST$claim0x' );
		$claim0a->setMainSnak( new PropertyValueSnak( 99, new StringValue( 'frob' ) ) );

		// same GUID as $claim0a, same content/hash as $claim0
		$claim0b = unserialize( serialize( $claim0 ) );
		$claim0b->setGuid( 'TEST$claim0x' );

		$source = new Claims();
		$target = new Claims();
		$expected = new Diff( array(), true );
		$argLists[] = array( $source, $target, $expected, 'Two empty lists should result in an empty diff' );


		$source = new Claims();
		$target = new Claims( array( $claim0 ) );
		$expected = new Diff( array( $claim0->getGuid() => new DiffOpAdd( $claim0 ) ), true );
		$argLists[] = array( $source, $target, $expected, 'List with no entries to list with one should result in one add op' );


		$source = new Claims( array( $claim0 ) );
		$target = new Claims();
		$expected = new Diff( array( $claim0->getGuid() => new DiffOpRemove( $claim0 ) ), true );
		$argLists[] = array( $source, $target, $expected, 'List with one entry to an empty list should result in one remove op' );


		$source = new Claims( array( $claim0, $claim3, $claim2 ) );
		$target = new Claims( array( $claim0, $claim2, $claim3 ) );
		$expected = new Diff( array(), true );
		$argLists[] = array( $source, $target, $expected, 'Two identical lists should result in an empty diff' );


		$source = new Claims( array( $claim0 ) );
		$target = new Claims( array( $claim1 ) );
		$expected = new Diff( array(
			$claim1->getGuid() => new DiffOpAdd( $claim1 ),
			$claim0->getGuid() => new DiffOpRemove( $claim0 )
		), true );
		$argLists[] = array( $source, $target, $expected, 'Two lists with each a single different entry should result into one add and one remove op' );


		$source = new Claims( array( $claim2, $claim3, $claim0, $claim4 ) );
		$target = new Claims( array( $claim2, $claim1, $claim3, $claim4 ) );
		$expected = new Diff( array(
			$claim1->getGuid() => new DiffOpAdd( $claim1 ),
			$claim0->getGuid() => new DiffOpRemove( $claim0 )
		), true );
		$argLists[] = array( $source, $target, $expected, 'Two lists with identical items except for one change should result in one add and one remove op' );


		$source = new Claims( array( $claim0, $claim0, $claim3, $claim2, $claim2, $claim2, $statement0 ) );
		$target = new Claims( array( $claim0, $claim0, $claim2, $claim3, $claim2, $claim2, $statement0 ) );
		$expected = new Diff( array(), true );
		$argLists[] = array( $source, $target, $expected, 'Two identical lists with duplicate items should result in an empty diff' );


		$source = new Claims( array( $statement0, $statement1, $claim0 ) );
		$target = new Claims( array( $claim1, $claim1, $claim0, $statement1 ) );
		$expected = new Diff( array(
			$claim1->getGuid() => new DiffOpAdd( $claim1 ),
			$statement0->getGuid() => new DiffOpRemove( $statement0 ),
		), true );
		$argLists[] = array( $source, $target, $expected, 'Two lists with duplicate items and a different entry should result into one add and one remove op' );

		$source = new Claims( array( $claim0, $claim3, $claim2 ) );
		$target = new Claims( array( $claim0, $claim2v2, $claim3 ) );
		$expected = new Diff( array( $claim2->getGuid() => new DiffOpChange( $claim2, $claim2v2 ) ), true );
		$argLists[] = array( $source, $target, $expected, 'Changing the value of a claim should result in a change op' );

		$source = new Claims( array( $claim0, $claim0a ) );
		$target = new Claims( array( $claim0, $claim0b ) );
		$expected = new Diff( array( $claim0a->getGuid() => new DiffOpChange( $claim0a, $claim0b ) ), true );
		$argLists[] = array( $source, $target, $expected, 'It should be possible for a claim to become the same as another claim' );

		return $argLists;
	}

	/**
	 * @dataProvider getDiffProvider
	 *
	 * @param \Wikibase\Claims $source
	 * @param \Wikibase\Claims $target
	 * @param Diff $expected
	 * @param string $message
	 */
	public function testGetDiff( Claims $source, Claims $target, Diff $expected, $message ) {
		$actual = $source->getDiff( $target );

		// Note: this makes order of inner arrays relevant, and this order is not guaranteed by the interface
		$this->assertEquals( $expected->getOperations(), $actual->getOperations(), $message );
	}

	public function testCallingGetClaimsForPropertyWithInvalidArgumentCausesException() {
		$claims = new Claims();

		$this->setExpectedException( 'InvalidArgumentException' );
		$claims->getClaimsForProperty( 'foo bar' );
	}

	public function testGetHash() {
		$claimsA = new Claims();
		$claimsB = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );
		$claim2 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P16" ) ) );

		$this->assertEquals( $claimsA->getHash(), $claimsB->getHash(), 'empty list' );

		$claimsA->addClaim( $claim1 );
		$claimsB->addClaim( $claim2 );
		$this->assertNotEquals( $claimsA->getHash(), $claimsB->getHash(), 'different content' );

		$claimsA->addClaim( $claim2 );
		$claimsB->addClaim( $claim1 );
		$this->assertNotEquals( $claimsA->getHash(), $claimsB->getHash(), 'different order' );

		$claimsA->removeClaim( $claim1 );
		$claimsB->removeClaim( $claim1 );
		$this->assertEquals( $claimsA->getHash(), $claimsB->getHash(), 'same content' );
	}

	public function testItrerator() {
		$claims = new Claims( array(
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P42" ) ) ),
			$this->makeClaim( new PropertySomeValueSnak( new PropertyId( "P42" ) ) ),
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P23" ) ) ),
			$this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P9000" ) ) ),
		) );

		$array = iterator_to_array( $claims->getIterator() );

		$this->assertSameSize( $claims, $array );

		reset( $array );
		reset( $claims );

		while ( $actual = current( $array ) ) {
			$expected = current( $claims );

			$this->assertEquals( $actual, $expected );

			next( $claims );
			next( $array );
		}
	}

	public function testIsEmpty() {
		$claims = new Claims();
		$claim1 = $this->makeClaim( new PropertyNoValueSnak( new PropertyId( "P15" ) ) );

		$this->assertTrue( $claims->isEmpty() );

		$claims->addClaim( $claim1 );
		$this->assertFalse( $claims->isEmpty() );

		$claims->removeClaim( $claim1 );
		$this->assertTrue( $claims->isEmpty() );
	}
}
