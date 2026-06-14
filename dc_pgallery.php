<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/DcGalleryRenderer.php';

class Dc_Pgallery extends Module
{
    public const CONFIG_KEYS = [
        'DC_PGALLERY_BASE_FOLDER',
        'DC_PGALLERY_MODE',
        'DC_PGALLERY_COLUMNS',
        'DC_PGALLERY_RATIO',
        'DC_PGALLERY_SLIDE_RATIO',
        'DC_PGALLERY_SLIDES_VISIBLE',
        'DC_PGALLERY_SPACE',
        'DC_PGALLERY_LOOP',
        'DC_PGALLERY_GLIGHTBOX_LOOP',
        'DC_PGALLERY_NAV_BG',
        'DC_PGALLERY_NAV_COLOR',
        'DC_PGALLERY_NAV_OPACITY',
        'DC_PGALLERY_NAV_HOVER_OPACITY',
    ];

    public function __construct()
    {
        $this->name = 'dc_pgallery';
        $this->tab = 'front_office_features';
        $this->version = '2.0.7';
        $this->author = 'Design Cart';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Design Cart Gallery');
        $this->description = $this->l('Image galleries via {dcgallery} shortcode in CMS and product content. GLightbox, Macy.js, Swiper.');
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('filterCmsContent')
            && $this->registerHook('filterCmsCategoryContent')
            && $this->registerHook('filterProductContent')
            && $this->registerHook('filterHtmlContent')
            && $this->registerHook('displayHeader')
            && $this->installConfiguration();
    }

    public function uninstall()
    {
        $this->uninstallConfiguration();

        return parent::uninstall();
    }

    protected function installConfiguration()
    {
        $defaults = [
            'DC_PGALLERY_BASE_FOLDER' => 'img/mygalleries',
            'DC_PGALLERY_MODE' => 'normal',
            'DC_PGALLERY_COLUMNS' => '4',
            'DC_PGALLERY_RATIO' => '3:4',
            'DC_PGALLERY_SLIDE_RATIO' => '16:9',
            'DC_PGALLERY_SLIDES_VISIBLE' => '3',
            'DC_PGALLERY_SPACE' => '16',
            'DC_PGALLERY_LOOP' => '1',
            'DC_PGALLERY_GLIGHTBOX_LOOP' => '1',
            'DC_PGALLERY_NAV_BG' => '#111111',
            'DC_PGALLERY_NAV_COLOR' => '#ffffff',
            'DC_PGALLERY_NAV_OPACITY' => '0.45',
            'DC_PGALLERY_NAV_HOVER_OPACITY' => '1',
        ];

        foreach ($defaults as $key => $value) {
            if (!Configuration::updateValue($key, $value)) {
                return false;
            }
        }

        return true;
    }

    protected function uninstallConfiguration()
    {
        foreach (self::CONFIG_KEYS as $key) {
            Configuration::deleteByName($key);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getGalleryConfig()
    {
        return [
            'base_folder' => Configuration::get('DC_PGALLERY_BASE_FOLDER'),
            'mode' => Configuration::get('DC_PGALLERY_MODE'),
            'columns' => Configuration::get('DC_PGALLERY_COLUMNS'),
            'ratio' => Configuration::get('DC_PGALLERY_RATIO'),
            'slide_ratio' => Configuration::get('DC_PGALLERY_SLIDE_RATIO'),
            'slides_visible' => Configuration::get('DC_PGALLERY_SLIDES_VISIBLE'),
            'space' => Configuration::get('DC_PGALLERY_SPACE'),
            'loop' => Configuration::get('DC_PGALLERY_LOOP'),
            'glightbox_loop' => Configuration::get('DC_PGALLERY_GLIGHTBOX_LOOP'),
            'nav_bg' => Configuration::get('DC_PGALLERY_NAV_BG'),
            'nav_color' => Configuration::get('DC_PGALLERY_NAV_COLOR'),
            'nav_opacity' => Configuration::get('DC_PGALLERY_NAV_OPACITY'),
            'nav_hover_opacity' => Configuration::get('DC_PGALLERY_NAV_HOVER_OPACITY'),
        ];
    }

    protected function getRenderer()
    {
        return new DcGalleryRenderer($this, $this->getGalleryConfig());
    }

    /**
     * Register CSS/JS on the front controller as soon as a shortcode is parsed.
     */
    public function registerFrontAssets(string $layoutMode = 'normal'): void
    {
        if (!isset($this->context->controller) || !is_object($this->context->controller)) {
            return;
        }

        $controller = $this->context->controller;
        if (!method_exists($controller, 'registerStylesheet')) {
            return;
        }

        static $registered = ['main' => false, 'glightbox' => false, 'swiper' => false, 'macy' => false];

        $base = 'modules/' . $this->name . '/media/';

        if (!$registered['main']) {
            $registered['main'] = true;
            $controller->registerStylesheet(
                'dc-pgallery-main',
                $base . 'css/dc_gallery.css',
                ['media' => 'all', 'priority' => 151]
            );
            $controller->registerJavascript(
                'dc-pgallery-front',
                $base . 'js/dc_pgallery_front.js',
                ['position' => 'bottom', 'priority' => 153, 'attributes' => 'defer']
            );
        }

        if (DcGalleryRenderer::$assetModes['glightbox'] && !$registered['glightbox']) {
            $registered['glightbox'] = true;
            $controller->registerStylesheet(
                'dc-pgallery-glightbox',
                $base . 'css/glightbox.min.css',
                ['media' => 'all', 'priority' => 150]
            );
            $controller->registerJavascript(
                'dc-pgallery-glightbox',
                $base . 'js/glightbox.min.js',
                ['position' => 'bottom', 'priority' => 150, 'attributes' => 'defer']
            );
        }

        $needsSwiper = !in_array($layoutMode, ['normal', 'tiles'], true);
        if ($needsSwiper && !$registered['swiper']) {
            $registered['swiper'] = true;
            $controller->registerStylesheet(
                'dc-pgallery-swiper',
                $base . 'swiper/swiper-bundle.min.css',
                ['media' => 'all', 'priority' => 152]
            );
            $controller->registerJavascript(
                'dc-pgallery-swiper',
                $base . 'swiper/swiper-bundle.min.js',
                ['position' => 'bottom', 'priority' => 151, 'attributes' => 'defer']
            );
        }

        if ($layoutMode === 'tiles' && !$registered['macy']) {
            $registered['macy'] = true;
            $controller->registerJavascript(
                'dc-pgallery-macy',
                $base . 'js/macy.js',
                ['position' => 'bottom', 'priority' => 152, 'attributes' => 'defer']
            );
        }
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitDcPgalleryConfig')) {
            $output .= $this->processConfiguration();
        }

        $output .= $this->renderConfigurationForm();
        $output .= $this->renderDocumentation();

        return $output;
    }

    protected function processConfiguration()
    {
        $keys = [
            'DC_PGALLERY_BASE_FOLDER' => Tools::getValue('DC_PGALLERY_BASE_FOLDER'),
            'DC_PGALLERY_MODE' => Tools::getValue('DC_PGALLERY_MODE'),
            'DC_PGALLERY_COLUMNS' => (string) (int) Tools::getValue('DC_PGALLERY_COLUMNS'),
            'DC_PGALLERY_RATIO' => Tools::getValue('DC_PGALLERY_RATIO'),
            'DC_PGALLERY_SLIDE_RATIO' => Tools::getValue('DC_PGALLERY_SLIDE_RATIO'),
            'DC_PGALLERY_SLIDES_VISIBLE' => (string) (int) Tools::getValue('DC_PGALLERY_SLIDES_VISIBLE'),
            'DC_PGALLERY_SPACE' => (string) (int) Tools::getValue('DC_PGALLERY_SPACE'),
            'DC_PGALLERY_LOOP' => Tools::getValue('DC_PGALLERY_LOOP') ? '1' : '0',
            'DC_PGALLERY_GLIGHTBOX_LOOP' => Tools::getValue('DC_PGALLERY_GLIGHTBOX_LOOP') ? '1' : '0',
            'DC_PGALLERY_NAV_BG' => Tools::getValue('DC_PGALLERY_NAV_BG'),
            'DC_PGALLERY_NAV_COLOR' => Tools::getValue('DC_PGALLERY_NAV_COLOR'),
            'DC_PGALLERY_NAV_OPACITY' => (string) Tools::getValue('DC_PGALLERY_NAV_OPACITY'),
            'DC_PGALLERY_NAV_HOVER_OPACITY' => (string) Tools::getValue('DC_PGALLERY_NAV_HOVER_OPACITY'),
        ];

        foreach ($keys as $key => $value) {
            Configuration::updateValue($key, $value);
        }

        return $this->displayConfirmation($this->l('Settings updated.'));
    }

    protected function renderConfigurationForm()
    {
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Base gallery folder'),
                        'name' => 'DC_PGALLERY_BASE_FOLDER',
                        'desc' => $this->l('Main directory for all galleries, relative to shop root. Example: img/mygalleries'),
                        'size' => 60,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Default gallery mode'),
                        'name' => 'DC_PGALLERY_MODE',
                        'desc' => $this->l('Presentation mode. Uses GLightbox, Macy.js and/or Swiper from the module media folder.'),
                        'options' => [
                            'query' => [
                                ['id' => 'normal', 'name' => $this->l('Normal (fixed ratio grid)')],
                                ['id' => 'tiles', 'name' => $this->l('Tiles (Macy masonry)')],
                                ['id' => 'slideshow', 'name' => $this->l('Slideshow (Swiper, 1 slide)')],
                                ['id' => 'carousel', 'name' => $this->l('Carousel (Swiper, multiple slides)')],
                                ['id' => 'coverflow', 'name' => $this->l('Coverflow effect')],
                                ['id' => 'cards', 'name' => $this->l('Cards effect')],
                                ['id' => 'thumbs', 'name' => $this->l('Gallery with thumbnails')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Columns (normal and tiles)'),
                        'name' => 'DC_PGALLERY_COLUMNS',
                        'desc' => $this->l('Number of grid columns in normal mode and for Macy.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Cell aspect ratio (normal mode)'),
                        'name' => 'DC_PGALLERY_RATIO',
                        'desc' => $this->l('Width:height of each cell in normal mode, e.g. 3:4, 1:1, 16:9'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Slide aspect ratio (Swiper)'),
                        'name' => 'DC_PGALLERY_SLIDE_RATIO',
                        'desc' => $this->l('Width:height of the main slide (slideshow, carousel, coverflow, cards, main image in thumbs).'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Visible slides (carousel)'),
                        'name' => 'DC_PGALLERY_SLIDES_VISIBLE',
                        'desc' => $this->l('How many slides are visible at once in carousel mode.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Space between slides (px)'),
                        'name' => 'DC_PGALLERY_SPACE',
                        'desc' => $this->l('Swiper spaceBetween value.'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Swiper loop'),
                        'name' => 'DC_PGALLERY_LOOP',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('GLightbox loop'),
                        'name' => 'DC_PGALLERY_GLIGHTBOX_LOOP',
                        'is_bool' => true,
                        'values' => [
                            ['id' => 'on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Navigation button background'),
                        'name' => 'DC_PGALLERY_NAV_BG',
                        'desc' => $this->l('Background color of prev/next buttons (hex), e.g. #111111'),
                    ],
                    [
                        'type' => 'color',
                        'label' => $this->l('Navigation icon color'),
                        'name' => 'DC_PGALLERY_NAV_COLOR',
                        'desc' => $this->l('Color of prev/next arrows (hex), e.g. #ffffff'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Navigation: default opacity'),
                        'name' => 'DC_PGALLERY_NAV_OPACITY',
                        'desc' => $this->l('Opacity of prev/next buttons without hover (0-1).'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Navigation: hover opacity'),
                        'name' => 'DC_PGALLERY_NAV_HOVER_OPACITY',
                        'desc' => $this->l('Opacity of prev/next buttons on gallery hover (0-1).'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->submit_action = 'submitDcPgalleryConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => [
                'DC_PGALLERY_BASE_FOLDER' => Configuration::get('DC_PGALLERY_BASE_FOLDER'),
                'DC_PGALLERY_MODE' => Configuration::get('DC_PGALLERY_MODE'),
                'DC_PGALLERY_COLUMNS' => Configuration::get('DC_PGALLERY_COLUMNS'),
                'DC_PGALLERY_RATIO' => Configuration::get('DC_PGALLERY_RATIO'),
                'DC_PGALLERY_SLIDE_RATIO' => Configuration::get('DC_PGALLERY_SLIDE_RATIO'),
                'DC_PGALLERY_SLIDES_VISIBLE' => Configuration::get('DC_PGALLERY_SLIDES_VISIBLE'),
                'DC_PGALLERY_SPACE' => Configuration::get('DC_PGALLERY_SPACE'),
                'DC_PGALLERY_LOOP' => (int) Configuration::get('DC_PGALLERY_LOOP'),
                'DC_PGALLERY_GLIGHTBOX_LOOP' => (int) Configuration::get('DC_PGALLERY_GLIGHTBOX_LOOP'),
                'DC_PGALLERY_NAV_BG' => Configuration::get('DC_PGALLERY_NAV_BG'),
                'DC_PGALLERY_NAV_COLOR' => Configuration::get('DC_PGALLERY_NAV_COLOR'),
                'DC_PGALLERY_NAV_OPACITY' => Configuration::get('DC_PGALLERY_NAV_OPACITY'),
                'DC_PGALLERY_NAV_HOVER_OPACITY' => Configuration::get('DC_PGALLERY_NAV_HOVER_OPACITY'),
            ],
        ];

        return $helper->generateForm([$form]);
    }

    protected function renderDocumentation()
    {
        return '<div class="panel">'
            . '<h3><i class="icon-info-circle"></i> ' . $this->l('DC Gallery - shortcode') . '</h3>'
            . '<div class="alert alert-info">'
            . '<p>' . $this->l('The source or sourc path is relative to the Base gallery folder.') . '</p>'
            . '<h4>' . $this->l('Modes (mode)') . '</h4>'
            . '<ul>'
            . '<li><code>normal</code> - ' . $this->l('fixed ratio grid + GLightbox') . '</li>'
            . '<li><code>tiles</code> - ' . $this->l('Macy masonry + GLightbox') . '</li>'
            . '<li><code>slideshow</code> - ' . $this->l('Swiper, one slide') . '</li>'
            . '<li><code>carousel</code> - ' . $this->l('Swiper, multiple slides + GLightbox') . '</li>'
            . '<li><code>coverflow</code> - ' . $this->l('Swiper coverflow + GLightbox') . '</li>'
            . '<li><code>cards</code> - ' . $this->l('Swiper cards + GLightbox') . '</li>'
            . '<li><code>thumbs</code> - ' . $this->l('main slide + 1:1 thumbnails + GLightbox') . '</li>'
            . '</ul>'
            . '<p class="help-block">' . $this->l('Ratio is width:height (e.g. 16:9 = wide, 3:4 = portrait). Value 1:3 means very tall cells.') . '</p>'
            . '<h4>' . $this->l('Examples') . '</h4>'
            . '<p><code>{dcgallery source=&quot;portfolio&quot; mode=&quot;normal&quot; columns=&quot;3&quot; ratio=&quot;3:4&quot;}</code><br>'
            . '<code>{dcgallery source=&quot;portfolio&quot; mode=&quot;normal&quot; columns=&quot;3&quot; ratio=&quot;16:9&quot;}</code><br>'
            . '<code>{dcgallery source=&quot;portfolio&quot; mode=&quot;tiles&quot; columns=&quot;4&quot;}</code><br>'
            . '<code>{dcgallery source=&quot;portfolio&quot; mode=&quot;carousel&quot; slides_visible=&quot;4&quot; slide_ratio=&quot;16:9&quot;}</code></p>'
            . '<p>' . $this->l('Works in CMS pages, product descriptions and other HTML content fields.') . '</p>'
            . '</div></div>';
    }

    public function hookFilterCmsContent(array $params)
    {
        if (empty($params['object']['content'])) {
            return $params;
        }

        $renderer = $this->getRenderer();
        $params['object']['content'] = $renderer->processContent((string) $params['object']['content']);

        return $params;
    }

    public function hookFilterCmsCategoryContent(array $params)
    {
        if (empty($params['object']['cms_category']['description'])) {
            return $params;
        }

        $renderer = $this->getRenderer();
        $params['object']['cms_category']['description'] = $renderer->processContent(
            (string) $params['object']['cms_category']['description']
        );

        return $params;
    }

    public function hookFilterProductContent(array $params)
    {
        if (empty($params['object'])) {
            return $params;
        }

        $renderer = $this->getRenderer();
        $object = $params['object'];

        foreach (['description', 'description_short'] as $field) {
            if (!empty($object[$field])) {
                $object[$field] = $renderer->processContent((string) $object[$field]);
            }
        }

        $params['object'] = $object;

        return $params;
    }

    public function hookFilterHtmlContent(array $params)
    {
        if (empty($params['object']) || empty($params['htmlFields']) || !is_array($params['htmlFields'])) {
            return $params;
        }

        $renderer = $this->getRenderer();

        foreach ($params['htmlFields'] as $field) {
            if (!empty($params['object'][$field])) {
                $params['object'][$field] = $renderer->processContent((string) $params['object'][$field]);
            }
        }

        return $params;
    }

    public function hookDisplayHeader()
    {
        if (DcGalleryRenderer::$assetsNeeded) {
            $mode = 'normal';
            if (DcGalleryRenderer::$assetModes['swiper']) {
                $mode = 'carousel';
            }
            if (DcGalleryRenderer::$assetModes['macy']) {
                $mode = 'tiles';
            }
            $this->registerFrontAssets($mode);
        }

        return '';
    }
}
