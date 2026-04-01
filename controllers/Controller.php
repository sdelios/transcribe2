<?php
class Controller {
    protected function render(string $viewPath, array $data = [], string $title = ''): void {
        // variables “limpias” para la vista
        extract($data, EXTR_SKIP);

        // título opcional para el layout
        $pageTitle = $title;

        ob_start();
        require __DIR__ . '/../views/' . $viewPath . '.php';
        $content = ob_get_clean();

        require __DIR__ . '/../layout.php';
    }
}
