<?php

declare (strict_types=1);
namespace Laminas\Service_Manager\Exception;

use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function implode;
use function reset;
use function serialize;
use function sort;
use function sprintf;
/** @final */
class Cyclic_Alias_Exception extends InvalidArgumentException
{
    /**
     * @param string   $alias conflicting alias key
     * @param array<string,string> $aliases map of referenced services, indexed by alias name
     */
    public static function from_cyclic_alias(string $alias, array $aliases): self
    {
        $cycle = $alias;
        $cursor = $alias;
        while (isset($aliases[$cursor]) && $aliases[$cursor] !== $alias) {
            $cursor = $aliases[$cursor];
            $cycle .= ' -> ' . $cursor;
        }
        $cycle .= ' -> ' . $alias . "\n";
        return new self(sprintf("A cycle was detected within the aliases definitions:\n%s", $cycle));
    }
    /**
     * @param array<string,string> $aliases map of referenced services, indexed by alias name (string)
     */
    public static function from_aliases_map(array $aliases): self
    {
        $detected_cycles = array_filter(array_map(static fn(string $alias): ?array => self::get_cycle_for($aliases, $alias), array_keys($aliases)));
        if (!$detected_cycles) {
            return new self(sprintf("A cycle was detected within the following aliases map:\n\n%s", self::print_references_map($aliases)));
        }
        return new self(sprintf("Cycles were detected within the provided aliases:\n\n%s\n\n" . "The cycle was detected in the following alias map:\n\n%s", self::print_cycles(self::de_duplicate_detected_cycles($detected_cycles)), self::print_references_map($aliases)));
    }
    /**
     * Retrieves the cycle detected for the given $alias, or `null` if no cycle was detected
     *
     * @param array<string,string> $aliases
     * @return array<string,true>|null
     */
    private static function get_cycle_for(array $aliases, string $alias): ?array
    {
        $cycle_candidate = [];
        $target_name = $alias;
        while (isset($aliases[$target_name])) {
            if (isset($cycle_candidate[$target_name])) {
                return $cycle_candidate;
            }
            $cycle_candidate[$target_name] = true;
            $target_name = $aliases[$target_name];
        }
        return null;
    }
    /**
     * @param array<string,string> $aliases
     */
    private static function print_references_map(array $aliases): string
    {
        $map = [];
        foreach ($aliases as $alias => $reference) {
            $map[] = '"' . $alias . '" => "' . $reference . '"';
        }
        return "[\n" . implode("\n", $map) . "\n]";
    }
    /**
     * @param string[][] $detectedCycles
     */
    private static function print_cycles(array $detected_cycles): string
    {
        return "[\n" . implode("\n", array_map(self::print_cycle(...), $detected_cycles)) . "\n]";
    }
    /**
     * @param string[] $detectedCycle
     */
    private static function print_cycle(array $detected_cycle): string
    {
        $full_cycle = array_keys($detected_cycle);
        $full_cycle[] = reset($full_cycle);
        return implode(' => ', array_map(static fn($cycle): string => '"' . $cycle . '"', $full_cycle));
    }
    /**
     * @param bool[][] $detectedCycles
     * @return bool[][] de-duplicated
     */
    private static function de_duplicate_detected_cycles(array $detected_cycles): array
    {
        $detected_cycles_by_hash = [];
        foreach ($detected_cycles as $detected_cycle) {
            $cycle_aliases = array_keys($detected_cycle);
            sort($cycle_aliases);
            $hash = serialize($cycle_aliases);
            $detected_cycles_by_hash[$hash] ??= $detected_cycle;
        }
        return array_values($detected_cycles_by_hash);
    }
}