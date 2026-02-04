<?php
/**
 * Plugin Name: Custom Login & Admin Restrict
 * Description: Кастомизация страницы входа и ограничение доступа к админке.
 * Version: 1.1.0
 * Author: korsakov-kuzjma
 * Author URI: https://github.com/korsakov-kuzjma
 * Text Domain: custom-login-restrict

 * Requires at least: 5.5
 * Requires PHP: 7.4
 * License: GPL-3.0-or-later
 */

namespace CustomLoginRestrict;

// Защита от прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

use WP_Error;

/**
 * Основной класс плагина
 */
class Plugin {
    private static $instance = null;

    /**
     * Приватный конструктор для предотвращения создания через new
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Получение единственного экземпляра (Singleton)
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Инициализация компонентов плагина
     */
    private function init(): void {
        // Запускаем сервисы на соответствующих хуках
        add_action('login_enqueue_scripts', [$this, 'load_login_customizer']);
        add_action('admin_init', [$this, 'load_admin_access_controller']);
        add_action('after_setup_theme', [$this, 'load_admin_bar_manager']);
    }

    /**
     * Загрузка кастомизации логина
     */
    public function load_login_customizer(): void {
        $customizer = new LoginCustomizer();
        $customizer->enqueue_styles();
        $customizer->add_inline_logo_style();
    }

    /**
     * Загрузка контроллера доступа к админке
     */
    public function load_admin_access_controller(): void {
        $controller = new AdminAccessController();
        $controller->restrict_access();
    }

    /**
     * Загрузка менеджера админ-бара
     */
    public function load_admin_bar_manager(): void {
        $manager = new AdminBarManager();
        $manager->hide_for_non_admins();
    }
}

/**
 * Сервис кастомизации страницы входа
 */
class LoginCustomizer {
    private const CSS_HANDLE = 'custom-login-css';
    private const CACHE_KEY = 'custom_login_site_icon_url';

    /**
     * Подключает CSS для страницы входа
     */
    public function enqueue_styles(): void {
        $css_path = $this->get_asset_path('css/custom-login-style.css');
        $css_url = $this->get_asset_url('css/custom-login-style.css');

        if (!$this->file_exists_cached($css_path)) {
            return;
        }

        wp_enqueue_style(
            self::CSS_HANDLE,
            $css_url,
            [],
            $this->get_file_version($css_path)
        );
    }

    /**
     * Добавляет инлайн-стиль с логотипом
     */
    public function add_inline_logo_style(): void {
        $logo_url = $this->get_site_icon_url();

        if (!$logo_url) {
            return;
        }

        $css = sprintf(
            '#login h1 a, .login h1 a { 
                background-image: url("%s") !important; 
                background-size: contain !important; 
                width: 100%% !important; 
                height: 80px !important; 
            }',
            esc_url($logo_url)
        );

        wp_add_inline_style(self::CSS_HANDLE, $css);
    }

    /**
     * URL логотипа ведёт на главную
     */
    public function get_logo_url(): string {
        return home_url('/');
    }

    /**
     * Подсказка логотипа — название сайта
     */
    public function get_logo_title(): string {
        return get_bloginfo('name');
    }

    /**
     * Получение пути к ресурсу
     */
    private function get_asset_path(string $relative_path): string {
        return plugin_dir_path(__FILE__) . 'assets/' . $relative_path;
    }

    /**
     * Получение URL ресурса
     */
    private function get_asset_url(string $relative_path): string {
        return plugin_dir_url(__FILE__) . 'assets/' . $relative_path;
    }

    /**
     * Кэшированная проверка существования файла
     */
    private function file_exists_cached(string $file_path): bool {
        static $cache = [];
        if (!isset($cache[$file_path])) {
            $cache[$file_path] = file_exists($file_path);
        }
        return $cache[$file_path];
    }

    /**
     * Получение версии файла с кэшированием
     */
    private function get_file_version(string $file_path): ?string {
        static $versions = [];
        if (!isset($versions[$file_path])) {
            $versions[$file_path] = $this->file_exists_cached($file_path) ? filemtime($file_path) : null;
        }
        return $versions[$file_path];
    }

    /**
     * Получение URL иконки сайта с кэшированием
     */
    private function get_site_icon_url(): ?string {
        $cached = wp_cache_get(self::CACHE_KEY, 'custom_login');
        if (false !== $cached) {
            return $cached;
        }

        $site_icon_id = (int) get_option('site_icon');
        $url = $site_icon_id > 0 ? wp_get_attachment_image_url($site_icon_id, 'full') : null;

        wp_cache_set(self::CACHE_KEY, $url, 'custom_login', HOUR_IN_SECONDS);

        return $url;
    }
}

/**
 * Контроллер ограничения доступа к админке
 */
class AdminAccessController {
    /**
     * Ограничение доступа к админке для не-администраторов
     */
    public function restrict_access(): void {
        // Разрешаем доступ только пользователям с правами управления
        if ($this->can_manage()) {
            return;
        }

        // Разрешаем AJAX, REST API, Cron
        if ($this->is_safe_request_context()) {
            return;
        }

        // Блокируем доступ с HTTP 403
        $this->forbid_access();
    }

    /**
     * Проверка прав пользователя
     */
    private function can_manage(): bool {
        return current_user_can('manage_options');
    }

    /**
     * Проверка безопасного контекста запроса
     */
    private function is_safe_request_context(): bool {
        return wp_doing_ajax() || wp_is_json_request() || wp_doing_cron();
    }

    /**
     * Отказ в доступе
     */
    private function forbid_access(): void {
        status_header(403);
        wp_die(
            esc_html__('Доступ запрещён.', 'custom-login-restrict'),
            esc_html__('Доступ запрещён', 'custom-login-restrict'),
            ['response' => 403]
        );
    }
}

/**
 * Менеджер админ-бара
 */
class AdminBarManager {
    /**
     * Скрывает админ-бар для не-администраторов
     */
    public function hide_for_non_admins(): void {
        if (!$this->can_manage()) {
            add_filter('show_admin_bar', '__return_false', 999);
        }
    }

    /**
     * Проверка прав пользователя
     */
    private function can_manage(): bool {
        return current_user_can('manage_options');
    }
}

// Инициализация плагина
add_action('plugins_loaded', [Plugin::class, 'get_instance']);

// Регистрация фильтров для логотипа
add_filter('login_headerurl', [new LoginCustomizer(), 'get_logo_url']);
add_filter('login_headertext', [new LoginCustomizer(), 'get_logo_title']);
