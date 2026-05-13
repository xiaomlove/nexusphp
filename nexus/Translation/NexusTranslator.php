<?php

namespace Nexus\Translation;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

class NexusTranslator
{
    protected Filesystem $filesystem;

    protected FileLoader $loader;

    protected Translator $translator;

    public function __construct(string $defaultLocale = 'en', string $fallbackLocale = 'en', ?string $defaultPath = null)
    {
        $this->filesystem = new Filesystem;
        $this->loader = new FileLoader($this->filesystem, $defaultPath ?? ROOT_PATH.'resources/lang');

        // Laravel-style fallback
        $this->translator = new Translator($this->loader, $defaultLocale);
        $this->translator->setFallback($fallbackLocale);
    }

    public function setLocale(string $locale): void
    {
        $this->translator->setLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    public function addNamespace(string $namespace, string $path): void
    {
        $this->loader->addNamespace($namespace, $path);
    }

    public function trans(string $key, array $replace = [], ?string $locale = null): string
    {
        return $this->translator->get($key, $replace, $locale);
    }

    public function has(string $key, ?string $locale = null): bool
    {
        return $this->translator->has($key, $locale);
    }
}
