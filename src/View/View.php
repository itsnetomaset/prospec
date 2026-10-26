<?php

declare(strict_types=1);

namespace Prospec\View;

/**
 * Plain PHP templates. Templates escape every dynamic value with e().
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private readonly string $directory)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Renders a template inside a layout.
     *
     * @param array<string, mixed> $data
     */
    public function page(string $template, array $data = [], string $layout = 'layout'): string
    {
        $content = $this->render($template, $data);
        return $this->render($layout, ['content' => $content] + $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->directory . '/' . $template . '.php';
        $render = function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__file;
        };
        ob_start();
        try {
            $render($file, $data + $this->shared + ['view' => $this]);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }
}
