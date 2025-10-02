<?php

namespace Backpack\Helpers\Traits;

use Illuminate\Support\Arr;

trait HasDisplayLabel
{
    /**
     * Ожидает, что модель вернёт конфиг вида:
     * [
     *   'parts'         => ['Заказ','ORD-1','2025-09-18 16:05','1 234.00 UAH'], // простой нумерованный массив
     *   'join'          => '-',                          // опционально
     *   'country'       => 'UA',                         // опционально; если пусто — флаг не выводим
     *   'html_template' => 'crud::columns.order_label',  // опционально
     * ]
     */
    abstract protected function displayLabelConfig(): array;

    /** Текстовая метка */
    public function getDisplayLabelAttribute(): string
    {
        $cfg   = $this->normalizeConfig($this->displayLabelConfig());
        $text  = $this->buildText($cfg['parts'], $cfg['join']);
        $flag  = $this->flagEmoji($cfg['country']);
        $prefix  = $cfg['prefix']? $cfg['prefix'] . ":&nbsp": '';

        return ltrim(trim(($flag ? $flag.' ' : '').$prefix.$text));
    }

    /** HTML-метка (опционально через кастомный blade) */
    public function getDisplayLabelHtmlAttribute(): string
    {
        $cfg  = $this->normalizeConfig($this->displayLabelConfig());
        $text = $this->buildText($cfg['parts'], $cfg['join']);
        $flag = $this->flagEmoji($cfg['country']);
        $prefix = $cfg['prefix'];

        $data = [
            'model' => $this,
            'prefix' => $prefix,
            'parts' => $cfg['parts'],
            'text'  => $text,
            'flag'  => $flag,      // emoji-строка или ''
            'cfg'   => $cfg,
        ];

        if ($tpl = $cfg['html_template']) {
            return view($tpl, $data)->render();
        }

        // дефолтный компактный HTML без шаблона
        return sprintf(
            '<span class="d-inline-flex align-items-center">%s<strong>%s</strong>&nbsp;/&nbsp;<span class="text-nowrap">%s</span></span>',
            $flag ? '<span class="mr-1" aria-hidden="true">'.$flag.'</span>' : '',
            $prefix,
            e($text)
        );
    }

    // ---- helpers ----

    protected function normalizeConfig(array $cfg): array
    {
        return [
            'prefix'        => Arr::get($cfg, 'prefix'),
            'parts'         => array_values(array_filter(Arr::get($cfg, 'parts', []), fn($v) => $v !== null && $v !== '')),
            'join'          => (string) (Arr::get($cfg, 'join', '-')),
            'country'       => (string) (Arr::get($cfg, 'country', '')),
            'html_template' => Arr::get($cfg, 'html_template'),
        ];
    }

    protected function buildText(array $parts, string $join): string
    {
        return implode($join, $parts);
    }

    /** Всегда emoji; если $cc пустой/не 2 буквы — вернёт '' */
    protected function flagEmoji(?string $cc): string
    {
        if (!$cc) return '';
        $cc = strtoupper($cc);
        if (strlen($cc) !== 2) return '';
        $base = 0x1F1E6;
        $a = mb_ord($cc[0]) - 65 + $base;
        $b = mb_ord($cc[1]) - 65 + $base;
        return mb_chr($a, 'UTF-8').mb_chr($b, 'UTF-8');
    }
}
