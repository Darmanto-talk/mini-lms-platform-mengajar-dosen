<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Markdown ringan yang aman untuk materi pelajaran.
 * Didukung: ```lang blok kode```, ## / ### judul, - / * daftar, 1. daftar bernomor,
 * | tabel |, **tebal**, `kode`, [teks](url), ![alt](gambar), tautan video YouTube/Vimeo.
 * Semua teks di-escape lebih dulu; hanya pola di atas yang diubah menjadi HTML.
 */
class LessonContent
{
    public static function render(?string $raw): HtmlString
    {
        if ($raw === null || trim($raw) === '') {
            return new HtmlString('<p class="muted">Belum ada materi untuk pelajaran ini.</p>');
        }

        $raw = str_replace("\r\n", "\n", $raw);
        $parts = preg_split('/```([a-z0-9]*)\n(.*?)```/is', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);

        $html = '';
        foreach ($parts as $i => $chunk) {
            $mod = $i % 3;
            if ($mod === 1) {
                continue; // nama bahasa
            }
            if ($mod === 2) {
                $html .= '<pre class="code">' . e(rtrim($chunk, "\n")) . '</pre>';
            } else {
                $html .= self::blocks($chunk);
            }
        }

        return new HtmlString($html);
    }

    public static function videoEmbed(?string $line): ?HtmlString
    {
        $line = trim((string) $line);
        if (preg_match('~^https?://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([\w-]{6,})~i', $line, $m)) {
            $src = 'https://www.youtube-nocookie.com/embed/' . $m[1];
        } elseif (preg_match('~^https?://(?:www\.)?vimeo\.com/(\d+)~i', $line, $m)) {
            $src = 'https://player.vimeo.com/video/' . $m[1];
        } else {
            return null;
        }

        return new HtmlString(
            '<div class="video-embed"><iframe src="' . e($src) . '" loading="lazy" allowfullscreen '
            . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
            . 'referrerpolicy="strict-origin-when-cross-origin"></iframe></div>'
        );
    }

    public static function dartpadUrl(?string $gist, string $theme = 'light'): string
    {
        $gist = preg_replace('/[^A-Za-z0-9]/', '', (string) $gist);
        $u = 'https://dartpad.dev/?theme=' . ($theme === 'dark' ? 'dark' : 'light') . '&split=50&run=true';
        if ($gist !== '') {
            $u .= '&id=' . $gist;
        }
        return $u;
    }

    private static function blocks(string $text): string
    {
        $out = '';
        foreach (preg_split('/\n{2,}/', trim($text)) as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            if (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)\)$/', $block, $m)) {
                $out .= self::figure($m[1], $m[2]);
                continue;
            }
            if (($embed = self::videoEmbed($block)) !== null) {
                $out .= $embed->toHtml();
                continue;
            }
            if (preg_match('/^(#{2,4})\s+(.*)$/', $block, $m)) {
                $lvl = strlen($m[1]);
                $out .= "<h{$lvl}>" . self::inline($m[2]) . "</h{$lvl}>";
                continue;
            }

            $lines = explode("\n", $block);

            if (count(array_filter($lines, fn ($l) => preg_match('/^\s*>\s?/', $l))) === count($lines)) {
                $inner = array_map(fn ($l) => self::inline(preg_replace('/^\s*>\s?/', '', $l)), $lines);
                $out .= '<blockquote>' . implode('<br>', $inner) . '</blockquote>';
                continue;
            }

            if (count(array_filter($lines, fn ($l) => preg_match('/^\s*[-*]\s+/', $l))) === count($lines)) {
                $out .= '<ul>' . implode('', array_map(
                    fn ($l) => '<li>' . self::inline(preg_replace('/^\s*[-*]\s+/', '', $l)) . '</li>',
                    $lines
                )) . '</ul>';
                continue;
            }
            if (count(array_filter($lines, fn ($l) => preg_match('/^\s*\d+\.\s+/', $l))) === count($lines)) {
                $out .= '<ol>' . implode('', array_map(
                    fn ($l) => '<li>' . self::inline(preg_replace('/^\s*\d+\.\s+/', '', $l)) . '</li>',
                    $lines
                )) . '</ol>';
                continue;
            }
            if (count($lines) >= 2
                && count(array_filter($lines, fn ($l) => str_contains($l, '|'))) === count($lines)
                && preg_match('/^\s*\|?[\s:-]*\|[\s:|-]*$/', $lines[1])) {
                $cells = fn ($row) => array_map('trim', explode('|', trim($row, " \t|")));
                $out .= '<div class="table-wrap"><table class="data"><thead><tr>';
                foreach ($cells($lines[0]) as $h) {
                    $out .= '<th>' . self::inline($h) . '</th>';
                }
                $out .= '</tr></thead><tbody>';
                foreach (array_slice($lines, 2) as $row) {
                    $out .= '<tr>';
                    foreach ($cells($row) as $c) {
                        $out .= '<td>' . self::inline($c) . '</td>';
                    }
                    $out .= '</tr>';
                }
                $out .= '</tbody></table></div>';
                continue;
            }

            $out .= '<p>' . implode('<br>', array_map([self::class, 'inline'], $lines)) . '</p>';
        }

        return $out;
    }

    public static function inline(string $s): string
    {
        $s = e($s);
        $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function ($m) {
            $src = html_entity_decode($m[2], ENT_QUOTES);
            if (! preg_match('#^https?://#i', $src)) {
                $src = asset(ltrim(preg_replace('#\.\.+#', '', $src), '/'));
            }
            return '<img class="lesson-img-inline" src="' . e($src) . '" alt="' . $m[1] . '" loading="lazy"'
                . ' onerror="this.dataset.broken=1;this.alt=\'Gambar tidak dapat dimuat: \'+this.src.split(\'/\').pop()">';
        }, $s);
        $s = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', fn ($m) =>
            '<a href="' . e(html_entity_decode($m[2], ENT_QUOTES)) . '" target="_blank" rel="noopener">' . $m[1] . '</a>', $s);
        $s = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
        $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);

        return $s;
    }

    private static function figure(string $alt, string $src): string
    {
        if (! preg_match('#^https?://#i', $src)) {
            $src = asset(ltrim(preg_replace('#\.\.+#', '', $src), '/'));
        }
        $cap = trim($alt) !== '' ? '<figcaption>' . e($alt) . '</figcaption>' : '';
        $file = e(basename(parse_url($src, PHP_URL_PATH) ?: $src));

        return '<figure class="lesson-img">'
            . '<img src="' . e($src) . '" alt="' . e($alt) . '" loading="lazy"'
            . ' onerror="this.closest(\'figure\').classList.add(\'img-missing\')">'
            . '<span class="img-missing-note">🖼️ Gambar belum tersedia — unggah berkas <code>' . $file . '</code> lewat tombol <strong>Unggah gambar</strong> di editor.</span>'
            . $cap . '</figure>';
    }
}
