<?php
/**
 * Plugin Name: Custom Login & Admin Restrict
 * Description: Кастомизация страницы входа (логотип, стили) и ограничение доступа к админ-панели для пользователей без прав администратора.
 * Version: 1.2.0
 * Author: korsakov-kuzjma
 * License: GPL-2.0-or-later
 * Text Domain: custom-login-restrict
 */

namespace CustomLoginRestrict;

if (!defined('ABSPATH')) {
    exit; // Защита от прямого вызова файла
}

/**
 * Основной класс плагина (Singleton)
 */
class Plugin {
    private static $instance = null;

    /**
     * Инициализация хуков
     */
    private function __construct() {
        // Кастомизация страницы логина
        add_action('login_enqueue_scripts', [$this, 'enqueue_login_assets']);
        add_filter('login_headerurl', [$this, 'get_logo_url']);
        add_filter('login_headertext', [$this, 'get_logo_title']);

        // Ограничение доступа к панели управления
        add_action('admin_init', [$this, 'restrict_admin_access']);
        add_action('after_setup_theme', [$this, 'manage_admin_bar']);
    }

    /**
     * Получение экземпляра класса
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Подключение CSS и инлайн-стилей логотипа
     */
    public function enqueue_login_assets() {
        $css_rel_path = 'assets/css/custom-login-style.css';
        $css_file_path = plugin_dir_path(__FILE__) . $css_rel_path;
        $css_url = plugins_url($css_rel_path, __FILE__);

        // Подключаем основной файл стилей
        if (file_exists($css_file_path)) {
            wp_enqueue_style(
                'custom-login-style',
                $css_url,
                [],
                filemtime($css_file_path) // Версия файла по дате изменения для сброса кэша
            );
        }

        // Добавляем логотип (иконку сайта) через инлайн-стили
        $site_icon_id = (int) get_option('site_icon');
        if ($site_icon_id) {
            $logo_url = wp_get_attachment_image_url($site_icon_id, 'full');
            $custom_css = sprintf(
                'body.login h1 a { 
                    background-image: url("%s") !important; 
                    background-size: contain !important; 
                    width: 100%% !important; 
                    height: 80px !important; 
                    margin-bottom: 20px !important;
                }',
                esc_url($logo_url)
            );
            wp_add_inline_style('custom-login-style', $custom_css);
        }
    }

    /**
     * URL логотипа ведет на главную страницу сайта
     */
    public function get_logo_url() {
        return home_url('/');
    }

    /**
     * Текст (атрибут title) логотипа — название сайта
     */
    public function get_logo_title() {
        return get_bloginfo('name');
    }

    /**
     * Запрет доступа в админку для всех, кроме администраторов
     */
    public function restrict_admin_access() {
        // Проверяем права и исключаем AJAX/Cron запросы
        if (!current_user_can('manage_options') && !wp_doing_ajax() && !wp_doing_cron()) {
            wp_safe_redirect(home_url());
            exit;
        }
    }

    /**
     * Скрытие админ-панели (Admin Bar) на фронтенде
     */
    public function manage_admin_bar() {
        if (!current_user_can('manage_options')) {
            show_admin_bar(false);
        }
    }
}

// Запуск плагина после загрузки всех плагинов
add_action('plugins_loaded', [Plugin::class, 'get_instance']);
