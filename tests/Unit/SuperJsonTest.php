<?php

namespace SulimanBenhalim\LaravelSuperJson\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use SulimanBenhalim\LaravelSuperJson\DataTypes\BigInt;
use SulimanBenhalim\LaravelSuperJson\DataTypes\SuperMap;
use SulimanBenhalim\LaravelSuperJson\DataTypes\SuperSet;
use SulimanBenhalim\LaravelSuperJson\SuperJson;
use SulimanBenhalim\LaravelSuperJson\Tests\TestCase;

/**
 * Core SuperJSON serialization and deserialization tests
 * Tests all supported data types and transformation behavior
 */
class SuperJsonTest extends TestCase
{
    /** @var SuperJson SuperJSON instance for testing */
    private SuperJson $superJson;

    /**
     * Set up SuperJSON instance before each test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->superJson = new SuperJson;
    }

    #[Test]
    public function it_serializes_and_deserializes_dates()
    {
        $date = new \DateTime('2023-10-05 12:00:00', new \DateTimeZone('UTC'));
        $data = ['timestamp' => $date];

        $serialized = $this->superJson->serialize($data);

        $this->assertArrayHasKey('json', $serialized);
        $this->assertArrayHasKey('meta', $serialized);
        $this->assertEquals(['Date'], $serialized['meta']['values']['timestamp']);

        $deserialized = $this->superJson->deserialize($serialized);

        $this->assertInstanceOf(\DateTimeInterface::class, $deserialized['timestamp']);
        $this->assertEquals(
            $date->format('Y-m-d H:i:s'),
            $deserialized['timestamp']->format('Y-m-d H:i:s')
        );
    }

    #[Test]
    public function it_handles_bigint_values()
    {
        $bigInt = new BigInt('12345678901234567890');
        $data = ['big_number' => $bigInt];

        $serialized = $this->superJson->serialize($data);

        $this->assertEquals('12345678901234567890', $serialized['json']['big_number']);
        $this->assertEquals(['bigint'], $serialized['meta']['values']['big_number']);

        $deserialized = $this->superJson->deserialize($serialized);

        $this->assertInstanceOf(BigInt::class, $deserialized['big_number']);
        $this->assertEquals('12345678901234567890', $deserialized['big_number']->toString());
    }

    #[Test]
    public function it_handles_sets()
    {
        $set = new SuperSet(['apple', 'banana', 'apple', 'cherry']);
        $data = ['fruits' => $set];

        $serialized = $this->superJson->serialize($data);

        // Set should remove duplicates
        $this->assertCount(3, $serialized['json']['fruits']);
        $this->assertContains('apple', $serialized['json']['fruits']);
        $this->assertContains('banana', $serialized['json']['fruits']);
        $this->assertContains('cherry', $serialized['json']['fruits']);

        $deserialized = $this->superJson->deserialize($serialized);

        $this->assertInstanceOf(SuperSet::class, $deserialized['fruits']);
        $this->assertEquals(3, $deserialized['fruits']->count());
    }

    #[Test]
    public function it_handles_maps()
    {
        $map = new SuperMap([
            ['key1', 'value1'],
            ['key2', 'value2'],
            [123, 'numeric key'],
        ]);
        $data = ['mapping' => $map];

        $serialized = $this->superJson->serialize($data);

        $this->assertCount(3, $serialized['json']['mapping']);
        $this->assertEquals(['key1', 'value1'], $serialized['json']['mapping'][0]);

        $deserialized = $this->superJson->deserialize($serialized);

        $this->assertInstanceOf(SuperMap::class, $deserialized['mapping']);
        $this->assertEquals('value1', $deserialized['mapping']->get('key1'));
        $this->assertEquals('numeric key', $deserialized['mapping']->get(123));
    }

    #[Test]
    public function it_handles_nested_structures()
    {
        $data = [
            'user' => [
                'name' => 'John Doe',
                'created_at' => new \DateTime('2023-01-01'),
                'settings' => new SuperMap([
                    ['theme', 'dark'],
                    ['language', 'en'],
                ]),
                'tags' => new SuperSet(['admin', 'user']),
            ],
        ];

        $serialized = $this->superJson->serialize($data);
        $deserialized = $this->superJson->deserialize($serialized);

        $this->assertEquals('John Doe', $deserialized['user']['name']);
        $this->assertInstanceOf(\DateTimeInterface::class, $deserialized['user']['created_at']);
        $this->assertInstanceOf(SuperMap::class, $deserialized['user']['settings']);
        $this->assertInstanceOf(SuperSet::class, $deserialized['user']['tags']);
    }

    #[Test]
    public function it_handles_plain_json_gracefully()
    {
        $plainJson = '{"name":"John","age":30}';

        $deserialized = $this->superJson->deserialize($plainJson);

        $this->assertEquals(['name' => 'John', 'age' => 30], $deserialized);
    }
}
