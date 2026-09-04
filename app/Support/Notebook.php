<?php

namespace App\Support;

use App\Models\Lesson;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Notebook ala Jupyter: badan pelajaran = daftar "sel".
 *
 * Jenis sel:
 *  - markdown   { source }                     teks kaya (LessonContent)
 *  - text       { source }                     paragraf polos
 *  - image      { src, caption }               satu gambar
 *  - video      { url }                        YouTube / Vimeo
 *  - code        { source, gist, run }         kode Dart (DartPad bila run=true)
 *  - callout     { variant, source }            kotak sorotan (info/tip/warning/danger)
 *  - practice    { prompt, starter, solution, gist }   latihan terpandu + DartPad
 *  - interactive { url, height }                embed interaktif (iframe sandbox)
 *  - quiz        {}                             menautkan ke kuis pelajaran
 *  - assignment  { prompt, rubric, max_score, project }   pengumpulan tugas / proyek
 */
class Notebook
{
    public const CONTENT_TYPES = ['markdown', 'text', 'image', 'video', 'code', 'callout', 'practice', 'interactive', 'file'];
    public const TYPES = ['markdown', 'text', 'image', 'video', 'code', 'callout', 'practice', 'interactive', 'file', 'quiz', 'assignment'];
    public const CALLOUT_VARIANTS = ['info', 'tip', 'warning', 'danger'];

    /** Label + ikon (emoji) tiap jenis sel — dipakai sebagai heading kartu. */
    public const CELL_META = [
        'markdown'    => ['Materi', '📄'],
        'text'        => ['Catatan', '📝'],
        'image'       => ['Gambar', '🖼️'],
        'video'       => ['Video', '🎬'],
        'code'        => ['Kode Dart', '💻'],
        'callout'     => ['Sorotan', '💡'],
        'practice'    => ['Latihan', '🎯'],
        'interactive' => ['Interaktif', '🧩'],
        'file'        => ['Dokumen', '📎'],
        'quiz'        => ['Kuis', '❓'],
        'assignment'  => ['Tugas', '🗂️'],
    ];

    /** Judul kartu untuk sebuah sel. */
    public static function cellLabel(array $cell): string
    {
        $t = $cell['type'] ?? '';

        if ($t === 'callout') {
            return ['info' => 'Info', 'tip' => 'Tips', 'warning' => 'Perhatian', 'danger' => 'Penting'][$cell['variant'] ?? 'info'] ?? 'Sorotan';
        }
        if ($t === 'assignment') {
            return ! empty($cell['project']) ? 'Proyek' : 'Tugas';
        }

        return self::CELL_META[$t][0] ?? 'Sel';
    }

    /** Ikon (emoji) untuk sebuah sel. */
    public static function cellIcon(array $cell): string
    {
        $t = $cell['type'] ?? '';

        if ($t === 'assignment' && ! empty($cell['project'])) {
            return '🚀';
        }

        return self::CELL_META[$t][1] ?? '•';
    }

    /** Daftar sel yang sudah dinormalisasi untuk sebuah pelajaran. */
    public static function normalize(Lesson $lesson): array
    {
        $raw = $lesson->cells;
        if (is_array($raw) && $raw !== []) {
            return self::sanitize($raw);
        }

        // Cadangan: bangun dari kolom lama.
        $cells = [];
        if (trim((string) $lesson->video_url) !== '') {
            $cells[] = ['type' => 'video', 'url' => (string) $lesson->video_url];
        }
        if (trim((string) $lesson->content) !== '') {
            $cells[] = ['type' => 'markdown', 'source' => (string) $lesson->content];
        }
        $gist = preg_replace('/[^A-Za-z0-9]/', '', (string) $lesson->dartpad_gist);
        if ($gist !== '' || $lesson->type === 'latihan') {
            $cells[] = ['type' => 'code', 'source' => '', 'gist' => $gist, 'run' => true];
        }
        if ($lesson->type === 'kuis') {
            $cells[] = ['type' => 'quiz'];
        }
        if ($lesson->type === 'tugas') {
            $cells[] = [
                'type' => 'assignment',
                'prompt' => '',
                'rubric' => (string) $lesson->rubric,
                'max_score' => (int) ($lesson->max_score ?: 100),
                'project' => false,
            ];
        }

        return $cells;
    }

    /** Bersihkan daftar sel mentah (dari editor / JSON impor) menjadi bentuk baku. */
    public static function sanitize(array $raw): array
    {
        $out = [];
        $seenQuiz = false;
        $seenAssignment = false;

        foreach ($raw as $c) {
            if (! is_array($c)) {
                continue;
            }
            $type = is_string($c['type'] ?? null) ? $c['type'] : '';

            switch ($type) {
                case 'markdown':
                case 'text':
                    $src = trim((string) ($c['source'] ?? ''));
                    if ($src === '') {
                        break;
                    }
                    $out[] = ['type' => $type, 'source' => $src];
                    break;

                case 'image':
                    $src = trim((string) ($c['src'] ?? ''));
                    if ($src === '') {
                        break;
                    }
                    $out[] = [
                        'type' => 'image',
                        'src' => $src,
                        'caption' => trim((string) ($c['caption'] ?? '')),
                    ];
                    break;

                case 'video':
                    $url = trim((string) ($c['url'] ?? ''));
                    if ($url === '') {
                        break;
                    }
                    $out[] = ['type' => 'video', 'url' => $url];
                    break;

                case 'code':
                    $out[] = [
                        'type' => 'code',
                        'source' => rtrim((string) ($c['source'] ?? ''), "\n"),
                        'gist' => preg_replace('/[^A-Za-z0-9]/', '', (string) ($c['gist'] ?? '')),
                        'run' => (bool) ($c['run'] ?? true),
                    ];
                    break;

                case 'callout':
                    $src = trim((string) ($c['source'] ?? ''));
                    if ($src === '') {
                        break;
                    }
                    $variant = in_array($c['variant'] ?? '', self::CALLOUT_VARIANTS, true) ? $c['variant'] : 'info';
                    $out[] = ['type' => 'callout', 'variant' => $variant, 'source' => $src];
                    break;

                case 'practice':
                    $prompt = trim((string) ($c['prompt'] ?? ''));
                    $starter = rtrim((string) ($c['starter'] ?? ''), "\n");
                    if ($prompt === '' && $starter === '') {
                        break;
                    }
                    $out[] = [
                        'type' => 'practice',
                        'prompt' => $prompt,
                        'starter' => $starter,
                        'solution' => rtrim((string) ($c['solution'] ?? ''), "\n"),
                        'gist' => preg_replace('/[^A-Za-z0-9]/', '', (string) ($c['gist'] ?? '')),
                    ];
                    break;

                case 'interactive':
                    $url = trim((string) ($c['url'] ?? ''));
                    if (! preg_match('#^https?://#i', $url)) {
                        break;
                    }
                    $out[] = [
                        'type' => 'interactive',
                        'url' => $url,
                        'height' => min(900, max(160, (int) ($c['height'] ?? 460))),
                    ];
                    break;

                case 'file':
                    $path = trim((string) ($c['path'] ?? ''));
                    // hanya izinkan berkas di dalam folder unggahan
                    $path = ltrim(preg_replace('#\.\.+#', '', $path), '/');
                    if ($path === '' || ! str_starts_with($path, 'uploads/')) {
                        break;
                    }
                    $ext = strtolower((string) ($c['ext'] ?? pathinfo($path, PATHINFO_EXTENSION)));
                    $out[] = [
                        'type' => 'file',
                        'path' => $path,
                        'name' => trim((string) ($c['name'] ?? '')) ?: pathinfo($path, PATHINFO_FILENAME),
                        'ext' => preg_replace('/[^a-z0-9]/', '', $ext),
                        'size' => max(0, (int) ($c['size'] ?? 0)),
                    ];
                    break;

                case 'quiz':
                    if ($seenQuiz) {
                        break;
                    }
                    $seenQuiz = true;
                    $out[] = ['type' => 'quiz'];
                    break;

                case 'assignment':
                    if ($seenAssignment) {
                        break;
                    }
                    $seenAssignment = true;
                    $out[] = [
                        'type' => 'assignment',
                        'prompt' => trim((string) ($c['prompt'] ?? '')),
                        'rubric' => trim((string) ($c['rubric'] ?? '')),
                        'max_score' => max(1, (int) ($c['max_score'] ?? 100)),
                        'project' => (bool) ($c['project'] ?? false),
                    ];
                    break;
            }
        }

        return $out;
    }

    /**
     * Kolom lama yang diturunkan dari daftar sel — supaya fitur lama
     * (AiGrader membaca lesson->rubric, pencarian, ekspor lama) tetap jalan.
     *
     * @return array{content:?string,video_url:?string,dartpad_gist:?string,rubric:?string,max_score:int,type:string}
     */
    public static function deriveLegacy(array $cells, string $fallbackType = 'materi'): array
    {
        $text = [];
        $video = null;
        $gist = null;
        $rubric = null;
        $maxScore = 100;
        $hasQuiz = false;
        $assignment = null;

        foreach ($cells as $c) {
            switch ($c['type']) {
                case 'markdown':
                case 'text':
                    $text[] = $c['source'];
                    break;
                case 'code':
                    if (trim((string) $c['source']) !== '') {
                        $text[] = "```dart\n".$c['source']."\n```";
                    }
                    if (! $gist && ($c['gist'] ?? '') !== '') {
                        $gist = $c['gist'];
                    }
                    break;
                case 'image':
                    $text[] = '!['.($c['caption'] ?? '').']('.$c['src'].')';
                    break;
                case 'file':
                    $text[] = '['.($c['name'] ?? 'Dokumen').'](/storage/'.$c['path'].')';
                    break;
                case 'callout':
                    $text[] = $c['source'];
                    break;
                case 'practice':
                    if (trim((string) ($c['prompt'] ?? '')) !== '') {
                        $text[] = $c['prompt'];
                    }
                    if (! $gist && ($c['gist'] ?? '') !== '') {
                        $gist = $c['gist'];
                    }
                    break;
                case 'video':
                    $video ??= $c['url'];
                    break;
                case 'quiz':
                    $hasQuiz = true;
                    break;
                case 'assignment':
                    $assignment = $c;
                    break;
            }
        }

        if ($assignment) {
            $rubric = $assignment['rubric'] !== '' ? $assignment['rubric'] : null;
            $maxScore = $assignment['max_score'];
            if ($assignment['prompt'] !== '') {
                array_unshift($text, $assignment['prompt']);
            }
        }

        // Tentukan "type" pelajaran (dipakai untuk gerbang lama & pelabelan).
        $contentCells = array_filter($cells, fn ($c) => in_array($c['type'], self::CONTENT_TYPES, true));
        if ($assignment) {
            $type = 'tugas';
        } elseif ($hasQuiz && ! $contentCells) {
            $type = 'kuis';
        } elseif ($hasQuiz) {
            $type = 'materi';
        } elseif (count($contentCells) === 1 && reset($contentCells)['type'] === 'video') {
            $type = 'video';
        } elseif ($gist !== null && ! array_filter($cells, fn ($c) => in_array($c['type'], ['markdown', 'text'], true))) {
            $type = 'latihan';
        } else {
            $type = $fallbackType === 'kuis' || $fallbackType === 'tugas' ? 'materi' : $fallbackType;
        }

        return [
            'content' => $text ? implode("\n\n", $text) : null,
            'video_url' => $video,
            'dartpad_gist' => $gist,
            'rubric' => $rubric,
            'max_score' => $maxScore,
            'type' => $type,
        ];
    }

    public static function hasType(array $cells, string $type): bool
    {
        foreach ($cells as $c) {
            if (($c['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
    }

    /** HTML untuk sel non-interaktif (markdown/text/image/video/code). Null untuk sel interaktif. */
    public static function renderCell(array $cell, string $mode = 'student'): ?HtmlString
    {
        // Mode present kini berlatar putih — DartPad & blok kode tetap tema terang.
        $dark = false;

        switch ($cell['type']) {
            case 'markdown':
                return LessonContent::render($cell['source']);

            case 'text':
                $paras = preg_split('/\n{2,}/', trim($cell['source']));
                $html = implode('', array_map(
                    fn ($p) => '<p>'.nl2br(e(trim($p))).'</p>',
                    array_filter($paras, fn ($p) => trim($p) !== '')
                ));

                return new HtmlString($html);

            case 'image':
                $src = $cell['src'];
                if (! preg_match('#^https?://#i', $src)) {
                    $src = asset(ltrim(preg_replace('#\.\.+#', '', $src), '/'));
                }
                $cap = trim((string) ($cell['caption'] ?? '')) !== ''
                    ? '<figcaption>'.e($cell['caption']).'</figcaption>' : '';

                return new HtmlString(
                    '<figure class="lesson-img"><img src="'.e($src).'" alt="'.e($cell['caption'] ?? '').'" loading="lazy">'.$cap.'</figure>'
                );

            case 'video':
                return LessonContent::videoEmbed($cell['url'])
                    ?? new HtmlString('<p><a class="btn" href="'.e($cell['url']).'" target="_blank" rel="noopener">&#9658; Buka video</a></p>');

            case 'code':
                $out = '';
                if (trim((string) $cell['source']) !== '') {
                    $cls = $dark ? 'code' : 'code';
                    $out .= '<pre class="'.$cls.'">'.e(rtrim($cell['source'], "\n")).'</pre>';
                }
                if (! empty($cell['run'])) {
                    $url = LessonContent::dartpadUrl($cell['gist'] ?? '', $dark ? 'dark' : 'light');
                    $out .= '<div class="dartpad mt2"><iframe src="'.e($url).'" title="DartPad" loading="lazy"></iframe></div>';
                    if ($mode === 'student') {
                        $out .= '<p class="hint">Ubah kode lalu klik <strong>Run</strong>. Butuh koneksi internet.</p>';
                    }
                }

                return new HtmlString($out !== '' ? $out : '<p class="muted">Sel kode kosong.</p>');

            case 'callout':
                return new HtmlString(
                    '<div class="nb-callout-body">'.LessonContent::render($cell['source'])->toHtml().'</div>'
                );

            case 'practice':
                $out = '<div class="nb-practice-body">';
                if (trim((string) ($cell['prompt'] ?? '')) !== '') {
                    $out .= '<div class="nb-practice-prompt">'.LessonContent::render($cell['prompt'])->toHtml().'</div>';
                }
                if (trim((string) ($cell['starter'] ?? '')) !== '') {
                    $out .= '<div class="small muted mt2">Kode awal:</div><pre class="code mt1">'.e($cell['starter']).'</pre>';
                }
                $purl = LessonContent::dartpadUrl($cell['gist'] ?? '', $dark ? 'dark' : 'light');
                $out .= '<div class="dartpad mt2"><iframe src="'.e($purl).'" title="DartPad" loading="lazy"></iframe></div>';
                if (trim((string) ($cell['solution'] ?? '')) !== '' && $mode !== 'present') {
                    $out .= '<details class="nb-practice-sol mt2"><summary>Lihat contoh solusi</summary>'
                        .'<pre class="code mt2">'.e($cell['solution']).'</pre></details>';
                }
                $out .= '</div>';

                return new HtmlString($out);

            case 'interactive':
                $h = (int) ($cell['height'] ?? 460);

                return new HtmlString(
                    '<div class="nb-embed" style="height:'.$h.'px">'
                    .'<iframe src="'.e($cell['url']).'" title="Konten interaktif" loading="lazy" '
                    .'sandbox="allow-scripts allow-same-origin allow-forms allow-popups" '
                    .'referrerpolicy="strict-origin-when-cross-origin"></iframe></div>'
                );

            case 'file':
                $url = asset('storage/'.$cell['path']);
                $ext = $cell['ext'] ?: 'file';
                $kind = match ($ext) {
                    'pdf' => 'pdf', 'doc', 'docx', 'odt', 'rtf' => 'doc',
                    'ppt', 'pptx', 'odp' => 'ppt', 'xls', 'xlsx', 'ods', 'csv' => 'xls',
                    'zip', 'rar' => 'zip', default => 'file',
                };
                $sizeTxt = self::humanSize((int) ($cell['size'] ?? 0));
                $isPdf = $ext === 'pdf';
                $out = '<div class="nb-file nb-file--'.$kind.'">'
                    .'<span class="nb-file-ic">'.strtoupper($ext).'</span>'
                    .'<span class="nb-file-main">'
                    .'<span class="nb-file-name">'.e($cell['name']).'</span>'
                    .'<span class="nb-file-meta">'.strtoupper($ext).($sizeTxt ? ' &middot; '.$sizeTxt : '').'</span>'
                    .'</span>';
                if ($isPdf && $mode !== 'present') {
                    $out .= '<button type="button" class="btn btn-sm btn-soft" data-pdf-modal="'.e($url).'" data-pdf-name="'.e($cell['name']).'">Buka</button>';
                }
                $out .= '<a class="btn btn-sm btn-cta" href="'.e($url).'" target="_blank" rel="noopener" download>Unduh</a>'
                    .'</div>';

                return new HtmlString($out);
        }

        return null;
    }

    private static function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return round($bytes / (1024 ** $i), $i > 1 ? 1 : 0).' '.$units[$i];
    }

    public static function newId(): string
    {
        return 'c'.Str::lower(Str::random(8));
    }
}
