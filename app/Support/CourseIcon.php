<?php

namespace App\Support;

/**
 * Logo/ikon mata kuliah — dipilih "acak" tetapi tetap sama setiap kali dibuka
 * (di-hash dari id mata kuliah), jadi tiap mata kuliah punya identitas visual
 * sendiri tanpa perlu mengunggah logo.
 */
class CourseIcon
{
    /** Ikon FontAwesome 6 Free (solid) bertema perkuliahan/keilmuan. */
    private const ICONS = [
        'code', 'laptop-code', 'diagram-project', 'database', 'microchip',
        'network-wired', 'cube', 'shapes', 'flask', 'calculator',
        'book-open', 'graduation-cap', 'lightbulb', 'terminal', 'sitemap', 'brain',
    ];

    /** Pasangan warna gradasi (terang → gelap) untuk latar ikon. */
    private const COLORS = [
        ['#60a5fa', '#2563eb'],   // biru
        ['#5eead4', '#0d9488'],   // toska
        ['#c4b5fd', '#7c3aed'],   // ungu
        ['#fdba74', '#ea580c'],   // oranye
        ['#f9a8d4', '#db2777'],   // merah muda
        ['#86efac', '#16a34a'],   // hijau
        ['#fca5a5', '#dc2626'],   // merah
        ['#fcd34d', '#d97706'],   // kuning
        ['#a5b4fc', '#4f46e5'],   // indigo
        ['#67e8f9', '#0891b2'],   // sian
    ];

    /**
     * @return array{icon:string, from:string, to:string}
     */
    public static function for(int|string $id): array
    {
        $icon = self::ICONS[crc32('mk-ic-'.$id) % count(self::ICONS)];
        [$from, $to] = self::COLORS[crc32('mk-cl-'.$id) % count(self::COLORS)];

        return ['icon' => $icon, 'from' => $from, 'to' => $to];
    }
}
