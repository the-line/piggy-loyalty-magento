<?php

declare(strict_types=1);

namespace Leat\Loyalty\Model;

class LoggerFormatter
{
    /**
     *
     */
    public function prepareForOutput($input, $quoteStrings = true)
    {
        $output = '';

        $quoteChar = ($quoteStrings) ? '"' : '';

        if ($input === true || $input === false) {
            $output = ($input === true) ? 'TRUE': 'FALSE';
        } elseif (is_numeric($input)) {
            $output = round($input, 10);
        } elseif ($input === null) {
            $output = 'NULL';
        } elseif (is_object($input)) {
            $output = sprintf('(object) "%s"', get_class($input));
        } elseif (is_array($input)) {
            $output = sprintf('%s%s%s', $quoteChar, print_r($input, true), $quoteChar);
        } else {
            $output = sprintf('%s%s%s', $quoteChar, (string) $input, $quoteChar);
        }

        return (string) $output;
    }
}
