<?php
class Controller {

    protected function requireAdmin(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if ((int)($_SESSION['auth']['iTipo'] ?? 0) !== 1) {
            http_response_code(403);
            exit('<p style="font-family:sans-serif;padding:2rem;color:#c00">Acceso denegado. Solo administradores pueden ver esta sección.</p>');
        }
    }

    protected function isAdmin(): bool {
        return (int)($_SESSION['auth']['iTipo'] ?? 0) === 1;
    }

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
