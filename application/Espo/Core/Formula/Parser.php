<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2023 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Core\Formula;

use Espo\Core\Formula\Exceptions\SyntaxError;

use stdClass;

/**
 * Parses a formula script into AST. Returns a RAW data object that represents a tree of functions.
 */
class Parser
{
    /**
     * @var array<int,string[]>
     */
    private $priorityList = [
        ['='],
        ['??'],
        ['||'],
        ['&&'],
        ['==', '!=', '>', '<', '>=', '<='],
        ['+', '-'],
        ['*', '/', '%'],
    ];

    /**
     * @var array<string,string>
     */
    private $operatorMap = [
        '=' => 'assign',
        '??' => 'comparison\\nullCoalescing',
        '||' => 'logical\\or',
        '&&' => 'logical\\and',
        '+' => 'numeric\\summation',
        '-' => 'numeric\\subtraction',
        '*' => 'numeric\\multiplication',
        '/' => 'numeric\\division',
        '%' => 'numeric\\modulo',
        '==' => 'comparison\\equals',
        '!=' => 'comparison\\notEquals',
        '>' => 'comparison\\greaterThan',
        '<' => 'comparison\\lessThan',
        '>=' => 'comparison\\greaterThanOrEquals',
        '<=' => 'comparison\\lessThanOrEquals',
    ];

    private string $variableNameRegExp = "/^[a-zA-Z0-9_\$]+$/";

    private string $functionNameRegExp = "/^[a-zA-Z0-9_\\\\]+$/";

    private string $attributeNameRegExp = "/^[a-zA-Z0-9.]+$/";

    public function parse(string $expression): stdClass
    {
        return $this->split($expression);
    }

    private function applyOperator(string $operator, string $firstPart, string $secondPart): stdClass
    {
        if ($operator === '=') {
            if (!strlen($firstPart)) {
                throw new SyntaxError("Bad operator usage.");
            }

            if ($firstPart[0] == '$') {
                $variable = substr($firstPart, 1);

                if ($variable === '' || !preg_match($this->variableNameRegExp, $variable)) {
                    throw new SyntaxError("Bad varable name `{$variable}`.");
                }

                return (object) [
                    'type' => 'assign',
                    'value' => [
                        (object) [
                            'type' => 'value',
                            'value' => $variable,
                        ],
                        $this->split($secondPart),
                    ]
                ];
            }

            if ($secondPart === '') {
                throw SyntaxError::create("Bad assignment usage.");
            }

            return (object) [
                'type' => 'setAttribute',
                'value' => [
                    (object) [
                        'type' => 'value',
                        'value' => $firstPart,
                    ],
                    $this->split($secondPart)
                ]
            ];
        }

        $functionName = $this->operatorMap[$operator];

        if ($functionName === '' || !preg_match($this->functionNameRegExp, $functionName)) {
            throw new SyntaxError("Bad function name `{$functionName}`.");
        }

        return (object) [
            'type' => $functionName,
            'value' => [
                $this->split($firstPart),
                $this->split($secondPart),
            ]
        ];
    }

    /**
     * @param ?int[] $splitterIndexList
     */
    private function processStrings(
        string &$string,
        string &$modifiedString,
        ?array &$splitterIndexList = null,
        bool $intoOneLine = false
    ): bool {

        $isString = false;
        $isSingleQuote = false;
        $isComment = false;
        $isLineComment = false;

        $modifiedString = $string;

        $braceCounter = 0;

        for ($i = 0; $i < strlen($string); $i++) {
            $isStringStart = false;

            if (!$isLineComment && !$isComment) {
                if ($string[$i] === "'" && ($i === 0 || $string[$i - 1] !== "\\")) {
                    if (!$isString) {
                        $isString = true;
                        $isSingleQuote = true;
                        $isStringStart = true;
                    } else {
                        if ($isSingleQuote) {
                            $isString = false;
                        }
                    }
                } else if ($string[$i] === "\"" && ($i === 0 || $string[$i - 1] !== "\\")) {
                    if (!$isString) {
                        $isString = true;
                        $isStringStart = true;
                        $isSingleQuote = false;
                    } else {
                        if (!$isSingleQuote) {
                            $isString = false;
                        }
                    }
                }
            }

            if ($isString) {
                if ($string[$i] === '(' || $string[$i] === ')') {
                    $modifiedString[$i] = '_';
                }
                else if (!$isStringStart) {
                    $modifiedString[$i] = ' ';
                }
            }
            else {
                if (!$isLineComment && !$isComment) {
                    if ($i && $string[$i] === '/' && $string[$i - 1] === '/') {
                        $isLineComment = true;
                    }

                    if (!$isLineComment) {
                        if ($i && $string[$i] === '*' && $string[$i - 1] === '/') {
                            $isComment = true;
                        }
                    }

                    if ($string[$i] === '(') {
                        $braceCounter++;
                    }

                    if ($string[$i] === ')') {
                        $braceCounter--;
                    }

                    if ($braceCounter === 0) {
                        if (!is_null($splitterIndexList)) {
                            if ($string[$i] === ';') {
                                $splitterIndexList[] = $i;
                            }
                        }

                        if ($intoOneLine) {
                            if ($string[$i] === "\r" || $string[$i] === "\n" || $string[$i] === "\t") {
                                $string[$i] = ' ';
                            }
                        }
                    }
                }

                if ($isLineComment) {
                    if ($string[$i] === "\n") {
                        $isLineComment = false;
                    }
                }

                if ($isComment) {
                    if ($string[$i - 1] === "*" && $string[$i] === "/") {
                        $isComment = false;
                    }
                }
            }
        }

        return $isString;
    }

    private function split(string $expression): stdClass
    {
        $expression = trim($expression);

        $braceCounter = 0;
        $hasExcessBraces = true;
        $modifiedExpression = '';
        $splitterIndexList = [];

        $isStringNotClosed = $this->processStrings($expression, $modifiedExpression, $splitterIndexList, true);

        if ($isStringNotClosed) {
            throw SyntaxError::create('String is not closed.');
        }

        $this->stripComments($expression, $modifiedExpression);

        foreach ($splitterIndexList as $i => $index) {
            if ($expression[$index] !== ';') {
                unset($splitterIndexList[$i]);
            }
        }

        $splitterIndexList = array_values($splitterIndexList);

        $expressionOutOfBraceList = [];

        for ($i = 0; $i < strlen($modifiedExpression); $i++) {
            if ($modifiedExpression[$i] === '(') {
                $braceCounter++;
            }

            if ($modifiedExpression[$i] === ')') {
                $braceCounter--;
            }

            if ($braceCounter === 0 && $i < strlen($modifiedExpression) - 1) {
                $hasExcessBraces = false;
            }

            if ($braceCounter === 0) {
                $expressionOutOfBraceList[] = true;
            } else {
                $expressionOutOfBraceList[] = false;
            }
        }

        if ($braceCounter !== 0) {
            throw SyntaxError::create(
                'Incorrect round brackets in expression ' . $expression . '.',
                'Incorrect round brackets.'
            );
        }

        if (
            strlen($expression) > 1 &&
            $expression[0] === '(' &&
            $expression[strlen($expression) - 1] === ')' &&
            $hasExcessBraces
        ) {
            $expression = substr($expression, 1, strlen($expression) - 2);

            return $this->split($expression);
        }

        if (count($splitterIndexList)) {
            if ($expression[strlen($expression) - 1] !== ';') {
                $splitterIndexList[] = strlen($expression);
            }

            $parsedPartList = [];

            for ($i = 0; $i < count($splitterIndexList); $i++) {
                if ($i > 0) {
                    $previousSplitterIndex = $splitterIndexList[$i - 1] + 1;
                }
                else {
                    $previousSplitterIndex = 0;
                }

                $part = trim(
                    substr(
                        $expression,
                        $previousSplitterIndex,
                        $splitterIndexList[$i] - $previousSplitterIndex
                    )
                );

                $parsedPartList[] = $this->parse($part);
            }
            return (object) [
                'type' => 'bundle',
                'value' => $parsedPartList,
            ];
        }

        $firstOperator = null;
        $minIndex = null;

        if (trim($expression) === '') {
            return (object) [
                'type' => 'value',
                'value' => null,
            ];
        }

        foreach ($this->priorityList as $operationList) {
            foreach ($operationList as $operator) {
                $startFrom = 1;

                while (true) {
                    $index = strpos($expression, $operator, $startFrom);

                    if ($index === false) {
                        break;
                    }

                    if ($expressionOutOfBraceList[$index]) {
                        break;
                    }

                    $startFrom = $index + 1;
                }

                if ($index !== false) {
                    $possibleRightOperator = null;

                    if (strlen($operator) === 1) {
                        if ($index < strlen($expression) - 1) {
                            $possibleRightOperator = trim($operator . $expression[$index + 1]);
                        }
                    }

                    if (
                        $possibleRightOperator &&
                        $possibleRightOperator != $operator &&
                        !empty($this->operatorMap[$possibleRightOperator])
                    ) {
                        continue;
                    }

                    $possibleLeftOperator = null;

                    if (strlen($operator) === 1) {
                        if ($index > 0) {
                            $possibleLeftOperator = trim($expression[$index - 1] . $operator);
                        }
                    }

                    if (
                        $possibleLeftOperator &&
                        $possibleLeftOperator != $operator &&
                        !empty($this->operatorMap[$possibleLeftOperator])
                    ) {
                        continue;
                    }

                    $firstPart = substr($expression, 0, $index);
                    $secondPart = substr($expression, $index + strlen($operator));

                    $modifiedFirstPart = $modifiedSecondPart = '';

                    $isString = $this->processStrings($firstPart, $modifiedFirstPart);

                    $this->processStrings($secondPart, $modifiedSecondPart);

                    if (
                        substr_count($modifiedFirstPart, '(') === substr_count($modifiedFirstPart, ')') &&
                        substr_count($modifiedSecondPart, '(') === substr_count($modifiedSecondPart, ')') &&
                        !$isString
                    ) {
                        if ($minIndex === null) {
                            $minIndex = $index;

                            $firstOperator = $operator;
                        }
                        else if ($index < $minIndex) {
                            $minIndex = $index;

                            $firstOperator = $operator;
                        }
                    }
                }
            }

            if ($firstOperator) {
                break;
            }
        }

        if ($firstOperator) {
            /** @var int $minIndex */

            $firstPart = substr($expression, 0, $minIndex);
            $secondPart = substr($expression, $minIndex + strlen($firstOperator));

            $firstPart = trim($firstPart);
            $secondPart = trim($secondPart);

            return $this->applyOperator($firstOperator, $firstPart, $secondPart);
        }

        $expression = trim($expression);

        if ($expression[0] === '!') {
            return (object) [
                'type' => 'logical\\not',
                'value' => $this->split(substr($expression, 1))
            ];
        }

        if ($expression[0] === '-') {
            return (object) [
                'type' => 'numeric\\subtraction',
                'value' => [
                    $this->split('0'),
                    $this->split(substr($expression, 1))
                ]
            ];
        }

        if ($expression[0] === '+') {
            return (object) [
                'type' => 'numeric\\summation',
                'value' => [
                    $this->split('0'),
                    $this->split(substr($expression, 1))
                ]
            ];
        }

        if (
            $expression[0] === "'" && $expression[strlen($expression) - 1] === "'"
            ||
            $expression[0] === "\"" && $expression[strlen($expression) - 1] === "\""
        ) {
            return (object) [
                'type' => 'value',
                'value' => substr($expression, 1, strlen($expression) - 2)
            ];
        }

        if ($expression[0] === "$") {
            $value = substr($expression, 1);

            if ($value === '' || !preg_match($this->variableNameRegExp, $value)) {
                throw new SyntaxError("Bad varable name `{$value}`.");
            }

            return (object) [
                'type' => 'variable',
                'value' => $value,
            ];
        }

        if (is_numeric($expression)) {
            $value = filter_var($expression, FILTER_VALIDATE_INT) !== false ?
                (int) $expression :
                (float) $expression;

            return (object) [
                'type' => 'value',
                'value' => $value,
            ];
        }

        if ($expression === 'true') {
            return (object) [
                'type' => 'value',
                'value' => true,
            ];
        }

        if ($expression === 'false') {
            return (object) [
                'type' => 'value',
                'value' => false,
            ];
        }

        if ($expression === 'null') {
            return (object) [
                'type' => 'value',
                'value' => null,
            ];
        }

        if ($expression[strlen($expression) - 1] === ')') {
            $firstOpeningBraceIndex = strpos($expression, '(');

            if ($firstOpeningBraceIndex > 0) {
                $functionName = trim(substr($expression, 0, $firstOpeningBraceIndex));
                $functionContent = substr($expression, $firstOpeningBraceIndex + 1, -1);

                $argumentList = $this->parseArgumentListFromFunctionContent($functionContent);

                $argumentSplittedList = [];

                foreach ($argumentList as $argument) {
                    $argumentSplittedList[] = $this->split($argument);
                }

                if ($functionName === '' || !preg_match($this->functionNameRegExp, $functionName)) {
                    throw new SyntaxError("Bad function name `{$functionName}`.");
                }

                return (object) [
                    'type' => $functionName,
                    'value' => $argumentSplittedList,
                ];
            }
        }

        if (!preg_match($this->attributeNameRegExp, $expression)) {
            throw SyntaxError::create("Attribute name `$expression` contains not allowed characters.");
        }

        if (substr($expression, -1) === '.') {
            throw SyntaxError::create("Attribute ends with dot.");
        }

        return (object) [
            'type' => 'attribute',
            'value' => $expression,
        ];
    }

    private function stripComments(string &$expression, string &$modifiedExpression): void
    {
        $commentIndexStart = null;

        for ($i = 0; $i < strlen($modifiedExpression); $i++) {
            if (is_null($commentIndexStart)) {
                if (
                    $modifiedExpression[$i] === '/' &&
                    $i < strlen($modifiedExpression) - 1 &&
                    $modifiedExpression[$i + 1] === '/'
                ) {
                    $commentIndexStart = $i;
                }
            }
            else {
                if ($modifiedExpression[$i] === "\n" || $i === strlen($modifiedExpression) - 1) {
                    for ($j = $commentIndexStart; $j <= $i; $j++) {
                        $modifiedExpression[$j] = ' ';

                        $expression[$j] = ' ';
                    }

                    $commentIndexStart = null;
                }
            }
        }

        for ($i = 0; $i < strlen($modifiedExpression) - 1; $i++) {
            if (is_null($commentIndexStart)) {
                if ($modifiedExpression[$i] === '/' && $modifiedExpression[$i + 1] === '*') {
                    $commentIndexStart = $i;
                }
            }
            else {
                if ($modifiedExpression[$i] === '*' && $modifiedExpression[$i + 1] === '/') {
                    for ($j = $commentIndexStart; $j <= $i + 1; $j++) {
                        $modifiedExpression[$j] = ' ';

                        $expression[$j] = ' ';
                    }

                    $commentIndexStart = null;
                }
            }
        }
    }

    /**
     * @return string[]
     */
    private function parseArgumentListFromFunctionContent(string $functionContent): array
    {
        $functionContent = trim($functionContent);

        $isString = false;
        $isSingleQuote = false;

        if ($functionContent === '') {
            return [];
        }

        $commaIndexList = [];
        $braceCounter = 0;

        for ($i = 0; $i < strlen($functionContent); $i++) {
            if ($functionContent[$i] === "'" && ($i === 0 || $functionContent[$i - 1] !== "\\")) {
                if (!$isString) {
                    $isString = true;
                    $isSingleQuote = true;
                }
                else {
                    if ($isSingleQuote) {
                        $isString = false;
                    }
                }
            }
            else if ($functionContent[$i] === "\"" && ($i === 0 || $functionContent[$i - 1] !== "\\")) {
                if (!$isString) {
                    $isString = true;
                    $isSingleQuote = false;
                }
                else {
                    if (!$isSingleQuote) {
                        $isString = false;
                    }
                }
            }

            if (!$isString) {
                if ($functionContent[$i] === '(') {
                    $braceCounter++;
                }
                else if ($functionContent[$i] === ')') {
                    $braceCounter--;
                }
            }

            if ($braceCounter === 0 && !$isString && $functionContent[$i] === ',') {
                $commaIndexList[] = $i;
            }
        }

        $commaIndexList[] = strlen($functionContent);

        $argumentList = [];

        for ($i = 0; $i < count($commaIndexList); $i++) {
            if ($i > 0) {
                $previousCommaIndex = $commaIndexList[$i - 1] + 1;
            }
            else {
                $previousCommaIndex = 0;
            }

            $argument = trim(
                substr(
                    $functionContent,
                    $previousCommaIndex,
                    $commaIndexList[$i] - $previousCommaIndex
                )
            );

            $argumentList[] = $argument;
        }

        return $argumentList;
    }
}
