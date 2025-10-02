<?php
if (!function_exists('get_flag')) {
    function get_flag(string|null $cc): string {
        if (!$cc) return '';
        $cc = strtoupper($cc);
        
        if ($cc === 'ZZ') {
          return "🌐"; // global
        }

        if (strlen($cc) !== 2) return '';
        $base = 0x1F1E6;
        $a = mb_ord($cc[0]) - 65 + $base;
        $b = mb_ord($cc[1]) - 65 + $base;
        return mb_chr($a, 'UTF-8').mb_chr($b, 'UTF-8');
    }
}