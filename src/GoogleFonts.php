<?php

namespace Spatie\GoogleFonts;

use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GoogleFonts
{
    protected Filesystem $filesystem;
    protected string     $path;
    protected bool       $inline;
    protected bool       $fallback;
    protected array      $fonts;
    protected string     $userAgent;

    public function __construct(
        Filesystem $filesystem,
        string $path,
        bool $inline,
        bool $fallback,
        string $userAgent,
        array $fonts
    )
    {
        $this->userAgent  = $userAgent;
        $this->fonts      = $fonts;
        $this->fallback   = $fallback;
        $this->inline     = $inline;
        $this->path       = $path;
        $this->filesystem = $filesystem;
    }

    public function load(string $font = 'default', bool $forceDownload = false): Fonts
    {
        if (!isset($this->fonts[$font])) {
            throw new RuntimeException("Font `{$font}` doesn't exist");
        }

        $url = $this->fonts[$font];

        try {
            if ($forceDownload) {
                return $this->fetch($url);
            }

            $fonts = $this->loadLocal($url);

            if (!$fonts) {
                return $this->fetch($url);
            }

            return $fonts;
        } catch (Exception $exception) {
            if (!$this->fallback) {
                throw $exception;
            }

            return new Fonts($url);
        }
    }

    protected function loadLocal(string $url): ?Fonts
    {
        if (!$this->filesystem->exists($this->path($url, 'fonts.css'))) {
            return null;
        }

        $localizedCss = $this->filesystem->get($this->path($url, 'fonts.css'));

        return new Fonts(
            $url,
            $this->filesystem->url($this->path($url, 'fonts.css')),
            $localizedCss,
            $this->inline
        );
    }

    protected function fetch(string $url): Fonts
    {
        $css = Http::withHeaders(['User-Agent' => $this->userAgent])
            ->get($url)
            ->body();

        $localizedCss = $css;

        foreach ($this->extractFontUrls($css) as $fontUrl) {
            $localizedFontUrl = $this->localizeFontUrl($fontUrl);

            $this->filesystem->put(
                $this->path($url, $localizedFontUrl),
                Http::get($fontUrl)->body()
            );

            $localizedCss = str_replace(
                $fontUrl,
                $this->filesystem->url($this->path($url, $localizedFontUrl)),
                $localizedCss
            );
        }

        $this->filesystem->put($this->path($url, 'fonts.css'), $localizedCss);

        return new Fonts(
            $url,
            $this->filesystem->url($this->path($url, 'fonts.css')),
            $localizedCss,
            $this->inline
        );
    }

    protected function extractFontUrls(string $css): array
    {
        $matches = [];
        preg_match_all('/url\((https:\/\/fonts.gstatic.com\/[^)]+)\)/', $css, $matches);

        return $matches[1] ?? [];
    }

    protected function localizeFontUrl(string $path): string
    {
        [$path, $extension] = explode('.', str_replace('https://fonts.gstatic.com/', '', $path));

        return implode('.', [Str::slug($path), $extension]);
    }

    protected function path(string $url, string $path = ''): string
    {
        return $this->path . '/' . substr(md5($url), 0, 10) . '/' . $path;
    }
}
