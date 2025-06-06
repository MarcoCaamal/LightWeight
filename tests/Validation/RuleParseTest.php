<?php

namespace LightWeight\Tests\Validation;

use LightWeight\Validation\Exceptions\RuleParseException;
use LightWeight\Validation\Exceptions\UnknownRuleException;
use LightWeight\Validation\Rule;
use LightWeight\Validation\Rules\Email;
use LightWeight\Validation\Rules\LessThan;
use LightWeight\Validation\Rules\Number;
use LightWeight\Validation\Rules\Required;
use LightWeight\Validation\Rules\RequiredWhen;
use LightWeight\Validation\Rules\RequiredWith;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RuleParseTest extends TestCase
{
    protected function setUp(): void
    {
        Rule::loadDefaultRules();
    }

    public static function basicRules(): array
    {
        return [
            [Email::class, 'email'],
            [Required::class, 'required'],
            [Number::class, 'number']
        ];
    }
    public static function rulesWithParameters()
    {
        return [
            [new LessThan(5), "less_than:5"],
            [new RequiredWith("other"), "required_with:other"],
            [new RequiredWhen("other", "=", "test"), "required_when:other,=,test"],
        ];
    }
    public static function rulesWithParametersWithError()
    {
        return [
            ["less_than"],
            ["less_than:"],
            ["required_with:"],
            ["required_when"],
            ["required_when:"],
            ["required_when:other"],
            ["required_when:other,"],
            ["required_when:other,="],
            ["required_when:other,=,"],
        ];
    }
    #[DataProvider("basicRules")]
    public function testParseBasicRules($class, $name)
    {
        $this->assertInstanceOf($class, Rule::from($name));
    }
    #[DataProvider('rulesWithParameters')]
    public function testParseRulesWithParameters($expected, $rule)
    {
        $this->assertEquals($expected, Rule::from($rule));
    }
    public function testParsingUnknownRulesThrowsUnkownRuleException()
    {
        $this->expectException(UnknownRuleException::class);
        Rule::from("unknown");
    }
    #[DataProvider('rulesWithParametersWithError')]
    public function testParsingRuleWithParametersWithoutPassingCorrectParametersThrowsRuleParseException($rule)
    {
        $this->expectException(RuleParseException::class);
        Rule::from($rule);
    }
}
