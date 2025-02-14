<?php

declare(strict_types=1);

namespace Doctrine\Tests\Common\Lexer;

use PHPUnit\Framework\TestCase;

use function array_map;
use function count;
use function setlocale;

use const LC_ALL;

class AbstractLexerTest extends TestCase
{
    /** @var ConcreteLexer */
    private $concreteLexer;

    public function setUp(): void
    {
        $this->concreteLexer = new ConcreteLexer();
    }

    public function tearDown(): void
    {
        setlocale(LC_ALL, null);
    }

    /**
     * @psalm-return list<array{string, list<array{value: string|int, type: string, position: int}>}>
     */
    public function dataProvider(): array
    {
        return [
            [
                'price=10',
                [
                    [
                        'value' => 'price',
                        'type' => 'string',
                        'position' => 0,
                    ],
                    [
                        'value' => '=',
                        'type' => 'operator',
                        'position' => 5,
                    ],
                    [
                        'value' => 10,
                        'type' => 'int',
                        'position' => 6,
                    ],
                ],
            ],
        ];
    }

    public function testResetPeek(): void
    {
        $expectedTokens = [
            [
                'value' => 'price',
                'type' => 'string',
                'position' => 0,
            ],
            [
                'value' => '=',
                'type' => 'operator',
                'position' => 5,
            ],
            [
                'value' => 10,
                'type' => 'int',
                'position' => 6,
            ],
        ];

        $this->concreteLexer->setInput('price=10');

        $this->assertEquals($expectedTokens[0], $this->concreteLexer->peek());
        $this->assertEquals($expectedTokens[1], $this->concreteLexer->peek());
        $this->concreteLexer->resetPeek();
        $this->assertEquals($expectedTokens[0], $this->concreteLexer->peek());
    }

    public function testResetPosition(): void
    {
        $expectedTokens = [
            [
                'value' => 'price',
                'type' => 'string',
                'position' => 0,
            ],
            [
                'value' => '=',
                'type' => 'operator',
                'position' => 5,
            ],
            [
                'value' => 10,
                'type' => 'int',
                'position' => 6,
            ],
        ];

        $this->concreteLexer->setInput('price=10');
        $this->assertNull($this->concreteLexer->lookahead);

        $this->assertTrue($this->concreteLexer->moveNext());
        $this->assertEquals($expectedTokens[0], $this->concreteLexer->lookahead);

        $this->assertTrue($this->concreteLexer->moveNext());
        $this->assertEquals($expectedTokens[1], $this->concreteLexer->lookahead);

        $this->concreteLexer->resetPosition(0);

        $this->assertTrue($this->concreteLexer->moveNext());
        $this->assertEquals($expectedTokens[0], $this->concreteLexer->lookahead);
    }

    /**
     * @psalm-param list<array{value: string|int, type: string, position: int}>  $expectedTokens
     *
     * @dataProvider dataProvider
     */
    public function testMoveNext(string $input, array $expectedTokens): void
    {
        $this->concreteLexer->setInput($input);
        $this->assertNull($this->concreteLexer->lookahead);

        for ($i = 0; $i < count($expectedTokens); $i++) {
            $this->assertTrue($this->concreteLexer->moveNext());
            $this->assertEquals($expectedTokens[$i], $this->concreteLexer->lookahead);
        }

        $this->assertFalse($this->concreteLexer->moveNext());
        $this->assertNull($this->concreteLexer->lookahead);
    }

    public function testSkipUntil(): void
    {
        $this->concreteLexer->setInput('price=10');

        $this->assertTrue($this->concreteLexer->moveNext());
        $this->concreteLexer->skipUntil('operator');

        $this->assertEquals(
            [
                'value' => '=',
                'type' => 'operator',
                'position' => 5,
            ],
            $this->concreteLexer->lookahead
        );
    }

    public function testUtf8Mismatch(): void
    {
        $this->concreteLexer->setInput("\xE9=10");

        $this->assertTrue($this->concreteLexer->moveNext());

        $this->assertEquals(
            [
                'value' => "\xE9=10",
                'type' => 'string',
                'position' => 0,
            ],
            $this->concreteLexer->lookahead
        );
    }

    /**
     * @psalm-param list<array{value: string|int, type: string, position: int}> $expectedTokens
     *
     * @dataProvider dataProvider
     */
    public function testPeek(string $input, array $expectedTokens): void
    {
        $this->concreteLexer->setInput($input);
        foreach ($expectedTokens as $expectedToken) {
            $this->assertEquals($expectedToken, $this->concreteLexer->peek());
        }

        $this->assertNull($this->concreteLexer->peek());
    }

    /**
     * @psalm-param list<array{value: string|int, type: string, position: int}> $expectedTokens
     *
     * @dataProvider dataProvider
     */
    public function testGlimpse(string $input, array $expectedTokens): void
    {
        $this->concreteLexer->setInput($input);

        foreach ($expectedTokens as $expectedToken) {
            $this->assertEquals($expectedToken, $this->concreteLexer->glimpse());
            $this->concreteLexer->moveNext();
        }

        $this->assertNull($this->concreteLexer->peek());
    }

    /**
     * @psalm-return list<array{string, int, string}>
     */
    public function inputUntilPositionDataProvider(): array
    {
        return [
            ['price=10', 5, 'price'],
        ];
    }

    /**
     * @dataProvider inputUntilPositionDataProvider
     */
    public function testGetInputUntilPosition(
        string $input,
        int $position,
        string $expectedInput
    ): void {
        $this->concreteLexer->setInput($input);

        $this->assertSame($expectedInput, $this->concreteLexer->getInputUntilPosition($position));
    }

    /**
     * @psalm-param list<array{value: string|int, type: string, position: int}> $expectedTokens
     *
     * @dataProvider dataProvider
     */
    public function testIsNextToken(string $input, array $expectedTokens): void
    {
        $this->concreteLexer->setInput($input);

        $this->concreteLexer->moveNext();
        for ($i = 0; $i < count($expectedTokens); $i++) {
            $this->assertTrue($this->concreteLexer->isNextToken($expectedTokens[$i]['type']));
            $this->concreteLexer->moveNext();
        }
    }

    /**
     * @psalm-param list<array{value: string|int, type: string, position: int}> $expectedTokens
     *
     * @dataProvider dataProvider
     */
    public function testIsNextTokenAny(string $input, array $expectedTokens): void
    {
        $allTokenTypes = array_map(static function ($token) {
            return $token['type'];
        }, $expectedTokens);

        $this->concreteLexer->setInput($input);

        $this->concreteLexer->moveNext();
        for ($i = 0; $i < count($expectedTokens); $i++) {
            $this->assertTrue($this->concreteLexer->isNextTokenAny([$expectedTokens[$i]['type']]));
            $this->assertTrue($this->concreteLexer->isNextTokenAny($allTokenTypes));
            $this->concreteLexer->moveNext();
        }
    }

    public function testGetLiteral(): void
    {
        $this->assertSame('Doctrine\Tests\Common\Lexer\ConcreteLexer::INT', $this->concreteLexer->getLiteral('int'));
        $this->assertSame('fake_token', $this->concreteLexer->getLiteral('fake_token'));
    }

    public function testIsA(): void
    {
        $this->assertTrue($this->concreteLexer->isA(11, 'int'));
        $this->assertTrue($this->concreteLexer->isA(1.1, 'int'));
        $this->assertTrue($this->concreteLexer->isA('=', 'operator'));
        $this->assertTrue($this->concreteLexer->isA('>', 'operator'));
        $this->assertTrue($this->concreteLexer->isA('<', 'operator'));
        $this->assertTrue($this->concreteLexer->isA('fake_text', 'string'));
    }

    public function testAddCatchablePatternsToMutableLexer(): void
    {
        $mutableLexer = new MutableLexer();
        $mutableLexer->addCatchablePattern('[a-z]');
        $mutableLexer->setInput('one');
        $token = $mutableLexer->glimpse();

        $this->assertEquals('o', $token['value']);

        $mutableLexer = new MutableLexer();
        $mutableLexer->addCatchablePattern('[a-z]+');
        $mutableLexer->setInput('one');
        $token = $mutableLexer->glimpse();

        $this->assertEquals('one', $token['value']);
    }

    public function testMarkerAnnotationLocaleTr(): void
    {
        setlocale(LC_ALL, 'tr_TR.utf8', 'tr_TR');
        $mutableLexer = new MutableLexer();
        $mutableLexer->addCatchablePattern('[a-z_\\\][a-z0-9_\:\\\]*[a-z_][a-z0-9_]*');
        $mutableLexer->addCatchablePattern('(?:[+-]?[0-9]+(?:[\.][0-9]+)*)(?:[eE][+-]?[0-9]+)?');
        $mutableLexer->addCatchablePattern('"(?:""|[^"])*+"');
        $mutableLexer->setInput('@ODM\Id');

        self::assertNull($mutableLexer->token);
        self::assertNull($mutableLexer->lookahead);
        self::assertTrue($mutableLexer->moveNext());
        self::assertNull($mutableLexer->token);
        self::assertEquals('@', $mutableLexer->lookahead['value']);
        self::assertTrue($mutableLexer->moveNext());
        self::assertEquals('@', $mutableLexer->token['value']);
        self::assertEquals('ODM\Id', $mutableLexer->lookahead['value']);
    }
}
