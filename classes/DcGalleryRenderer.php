<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class DcGalleryRenderer
{
    /** @var Dc_Pgallery */
    private $module;

    /** @var array<string, mixed> */
    private $config;

    private $instanceCounter = 0;

    /** @var bool */
    public static $assetsNeeded = false;

    /** @var array<string, bool> */
    public static $assetModes = [
        'base' => false,
        'swiper' => false,
        'macy' => false,
        'glightbox' => false,
    ];

    /**
     * @param Dc_Pgallery $module
     * @param array<string, mixed> $config
     */
    public function __construct($module, array $config)
    {
        $this->module = $module;
        $this->config = $config;
    }

    public function processContent(string $content): string
    {
        if ($content === '' || stripos($content, '{dcgallery') === false) {
            return $content;
        }

        $content = $this->unwrapGalleryShortcodeWrappers($content);

        $pattern = '/\{dcgallery\s+([^}]*)\}/i';

        return (string) preg_replace_callback($pattern, function ($matches) {
            $attributes = $this->parseAttributes($matches[1] ?? '');

            return $this->renderGallery($attributes);
        }, $content);
    }

    /**
     * TinyMCE often wraps shortcodes in <p><code>…</code></p>. Block gallery inside <code>
     * breaks the DOM: browser nests all items in one inline <code>, grid becomes one column.
     */
    private function unwrapGalleryShortcodeWrappers(string $content): string
    {
        $patterns = [
            '/<p>\s*<code>\s*(\{dcgallery\s+[^}]*\})\s*<\/code>\s*<\/p>/iu',
            '/<code>\s*(\{dcgallery\s+[^}]*\})\s*<\/code>/iu',
            '/<p>\s*(\{dcgallery\s+[^}]*\})\s*<\/p>/iu',
        ];

        foreach ($patterns as $pattern) {
            $content = (string) preg_replace($pattern, '$1', $content);
        }

        return $content;
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes(string $attributeString): array
    {
        $attributes = [];
        $regex = '/([a-zA-Z0-9_-]+)\s*=\s*([\'"“”])([^\'"“”]*)\2/';

        if (!preg_match_all($regex, $attributeString, $matches, PREG_SET_ORDER)) {
            return $attributes;
        }

        foreach ($matches as $match) {
            $key = Tools::strtolower(trim($match[1]));
            $value = trim($match[3]);
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    /**
     * @param array<string, string> $atts
     */
    private function renderGallery(array $atts): string
    {
        $defaults = [
            'mode' => (string) ($this->config['mode'] ?? 'normal'),
            'columns' => (string) ($this->config['columns'] ?? '4'),
            'ratio' => (string) ($this->config['ratio'] ?? '3:4'),
            'slide_ratio' => (string) ($this->config['slide_ratio'] ?? '16:9'),
            'slides_visible' => (string) ($this->config['slides_visible'] ?? '3'),
            'space' => (string) ($this->config['space'] ?? '16'),
            'loop' => (string) ($this->config['loop'] ?? '1'),
            'glightbox_loop' => (string) ($this->config['glightbox_loop'] ?? '1'),
            'nav_bg' => (string) ($this->config['nav_bg'] ?? '#111111'),
            'nav_color' => (string) ($this->config['nav_color'] ?? '#ffffff'),
            'nav_opacity' => (string) ($this->config['nav_opacity'] ?? '0.45'),
            'nav_hover_opacity' => (string) ($this->config['nav_hover_opacity'] ?? '1'),
        ];

        $source = $atts['source'] ?? $atts['sourc'] ?? '';
        $source = trim($source, '/');

        if ($source === '') {
            return '<!-- ' . htmlspecialchars($this->module->l('DC Gallery: missing source or sourc attribute'), ENT_QUOTES, 'UTF-8') . ' -->';
        }

        $options = array_merge($defaults, $atts);
        $images = $this->getImagesForSource($source);

        if ($images === []) {
            return '<!-- ' . htmlspecialchars(
                sprintf($this->module->l('DC Gallery: no images in folder %s'), $source),
                ENT_QUOTES,
                'UTF-8'
            ) . ' -->';
        }

        $this->instanceCounter++;
        $containerId = 'dc-gallery-' . $this->instanceCounter;
        $layoutMode = $this->normalizeLayoutMode($options['mode']);

        $this->loadAssetsForMode($layoutMode);

        $columns = $this->normalizeColumns($options['columns']);
        $cellRatio = (string) $options['ratio'];
        $slideRatio = (string) $options['slide_ratio'];
        $slidesVisible = $this->normalizeSlidesVisible($options['slides_visible']);
        $space = $this->normalizeSpace($options['space']);
        $loopSwiper = $this->toBool($options['loop'], true);
        $glightboxLoop = $this->toBool($options['glightbox_loop'], true);
        $navBg = $this->normalizeColor($options['nav_bg'], '#111111');
        $navColor = $this->normalizeColor($options['nav_color'], '#ffffff');
        $navOpacity = $this->normalizeOpacity($options['nav_opacity'], 0.45);
        $navHoverOpacity = $this->normalizeOpacity($options['nav_hover_opacity'], 1.0);

        $html = $this->renderLayout(
            $containerId,
            $layoutMode,
            $images,
            $columns,
            $cellRatio,
            $slideRatio,
            $navBg,
            $navColor,
            $navOpacity,
            $navHoverOpacity,
            $glightboxLoop,
            $slidesVisible,
            $space,
            $loopSwiper,
            count($images)
        );

        return '<div class="dc-gallery-wrap">' . $html . $this->renderPoweredBy() . '</div>';
    }

    private function renderPoweredBy(): string
    {
        $logoUrl = $this->module->getPathUri() . 'logo.png';
        $link = 'https://www.designcart.pl/';
        $alt = $this->module->l('powered by Design Cart');
        $logoStyle = 'display:inline-block;height:16px;width:auto;max-width:16px;max-height:16px;'
            . 'margin:0;padding:0;border:0;object-fit:contain;vertical-align:middle;flex:0 0 auto;';

        return '<div class="dc-gallery-powered">'
            . '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '"'
            . ' target="_blank" rel="noopener noreferrer"'
            . ' title="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '">'
            . '<span class="dc-gallery-powered__text">powered by</span>'
            . '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '"'
            . ' alt="' . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . '"'
            . ' class="dc-gallery-powered__logo" width="16" height="16" loading="lazy" decoding="async"'
            . ' style="' . htmlspecialchars($logoStyle, ENT_QUOTES, 'UTF-8') . '">'
            . '</a>'
            . '</div>';
    }

    /**
     * @return array<int, array{url: string, title: string}>
     */
    private function getImagesForSource(string $source): array
    {
        $baseFolder = trim((string) ($this->config['base_folder'] ?? 'img/mygalleries'), '/');
        $fullRelative = trim($baseFolder . '/' . $source, '/');

        $siteRoot = rtrim(_PS_ROOT_DIR_, '/');
        $fullPath = realpath($siteRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $fullRelative));

        if ($fullPath === false || strpos($fullPath, $siteRoot) !== 0 || !is_dir($fullPath)) {
            return [];
        }

        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
        $files = [];
        foreach (scandir($fullPath) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $absoluteFile = $fullPath . DIRECTORY_SEPARATOR . $file;
            if (!is_file($absoluteFile)) {
                continue;
            }
            $ext = Tools::strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions, true)) {
                $files[] = $absoluteFile;
            }
        }
        sort($files);

        $baseUri = rtrim(__PS_BASE_URI__, '/');
        $images = [];
        foreach ($files as $absoluteFile) {
            if (!is_file($absoluteFile)) {
                continue;
            }
            $relative = ltrim(str_replace($siteRoot . DIRECTORY_SEPARATOR, '', $absoluteFile), DIRECTORY_SEPARATOR);
            $url = $baseUri . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            $title = pathinfo($absoluteFile, PATHINFO_FILENAME);

            $images[] = [
                'url' => $url,
                'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            ];
        }

        return $images;
    }

    private function loadAssetsForMode(string $mode): void
    {
        self::$assetsNeeded = true;
        self::$assetModes['base'] = true;

        $needsSwiper = !in_array($mode, ['normal', 'tiles'], true);
        if ($needsSwiper) {
            self::$assetModes['swiper'] = true;
        }

        if ($mode === 'tiles') {
            self::$assetModes['macy'] = true;
        }

        if ($this->modeSupportsLightbox($mode)) {
            self::$assetModes['glightbox'] = true;
        }

        $this->module->registerFrontAssets($mode);
    }

    private function modeSupportsLightbox(string $mode): bool
    {
        return in_array($mode, ['normal', 'tiles', 'carousel'], true);
    }

    /**
     * @param array<int, array{url: string, title: string}> $images
     */
    private function renderLayout(
        string $containerId,
        string $mode,
        array $images,
        int $columns,
        string $cellRatio,
        string $slideRatio,
        string $navBg,
        string $navColor,
        float $navOpacity,
        float $navHoverOpacity,
        bool $glightboxLoop,
        int $slidesVisible,
        int $space,
        bool $loopSwiper,
        int $imageCount
    ): string {
        $navStyle = '--dcg-nav-bg:' . $navBg . ';--dcg-nav-color:' . $navColor
            . ';--dcg-nav-opacity:' . $navOpacity . ';--dcg-nav-hover-opacity:' . $navHoverOpacity . ';';
        $dataAttrs = $this->buildDataAttributes(
            $mode,
            $columns,
            $space,
            $slidesVisible,
            $loopSwiper,
            $imageCount,
            $glightboxLoop
        );

        switch ($mode) {
            case 'normal':
                $cssVars = '--dcg-cols:' . $columns . ';--dcg-aspect:' . $this->ratioToAspectCss($cellRatio) . ';';
                $html = '<div id="' . $containerId . '" class="dc-gallery dc-gallery--normal" style="' . htmlspecialchars($cssVars, ENT_QUOTES, 'UTF-8') . '"' . $dataAttrs . '>';
                foreach ($images as $image) {
                    $html .= $this->renderImageAnchor($image, $containerId);
                }
                $html .= '</div>';

                return $html;

            case 'tiles':
                $html = '<div id="' . $containerId . '" class="dc-gallery dc-gallery--tiles"' . $dataAttrs . '>';
                $html .= '<div class="dcg-macy-inner">';
                foreach ($images as $image) {
                    $html .= '<div class="dcg-macy-item">' . $this->renderImageAnchor($image, $containerId) . '</div>';
                }
                $html .= '</div></div>';

                return $html;

            case 'thumbs':
                $style = $navStyle;
                $html = '<div id="' . $containerId . '" class="dc-gallery dc-gallery--thumbs" style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"' . $dataAttrs . '>';
                $html .= '<div class="swiper dcg-swiper-main"><div class="swiper-wrapper" id="' . $containerId . '-main-wrapper">';
                foreach ($images as $image) {
                    $imgStyle = $this->buildFitImgStyle($slideRatio, '10px');
                    $html .= '<div class="swiper-slide">' . $this->renderImageAnchor($image, $containerId, 'dcg-slide-link', $imgStyle, false) . '</div>';
                }
                $html .= '</div>';
                $html .= $this->renderSwiperNavigationButtons($containerId . '-main-wrapper');
                $html .= '<div class="swiper-pagination"></div>';
                $html .= '</div>';
                $html .= '<div class="swiper dcg-swiper-thumbs"><div class="swiper-wrapper">';
                foreach ($images as $image) {
                    $imgStyle = $this->buildFitImgStyle('1:1', '8px');
                    $html .= '<div class="swiper-slide">' . $this->renderImageAnchor($image, $containerId, 'dcg-thumb-link', $imgStyle, false) . '</div>';
                }
                $html .= '</div></div></div>';

                return $html;

            default:
                return $this->renderSwiperLayout($containerId, $mode, $images, $containerId, $slideRatio, $navStyle, $dataAttrs);
        }
    }

    private function buildDataAttributes(
        string $mode,
        int $columns,
        int $space,
        int $slidesVisible,
        bool $loopSwiper,
        int $imageCount,
        bool $glightboxLoop
    ): string {
        $loop = '0';
        if ($mode === 'carousel' && $loopSwiper && $imageCount > $slidesVisible) {
            $loop = '1';
        } elseif ($mode !== 'carousel' && $mode !== 'normal' && $mode !== 'tiles' && $loopSwiper && $imageCount > 1) {
            $loop = '1';
        }

        $glightboxLoopAttr = $this->modeSupportsLightbox($mode)
            ? ' data-dcg-glightbox-loop="' . ($glightboxLoop ? '1' : '0') . '"'
            : '';

        return ' data-dcg-mode="' . htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-dcg-columns="' . $columns . '"'
            . ' data-dcg-space="' . $space . '"'
            . ' data-dcg-slides-visible="' . $slidesVisible . '"'
            . ' data-dcg-loop="' . $loop . '"'
            . $glightboxLoopAttr;
    }

    /**
     * @param array{url: string, title: string} $image
     */
    private function renderImageAnchor(
        array $image,
        string $galleryId,
        string $extraClass = '',
        ?string $imgStyle = null,
        bool $withLightbox = true
    ): string {
        $imgTag = '<img src="' . $image['url'] . '" alt="' . $image['title'] . '" loading="lazy" decoding="async"';
        if ($imgStyle !== null) {
            $imgTag .= ' style="' . htmlspecialchars($imgStyle, ENT_QUOTES, 'UTF-8') . '"';
        }
        $imgTag .= '>';

        if (!$withLightbox) {
            $classes = trim('dcg-slide-media ' . $extraClass);

            return '<span class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '">' . $imgTag . '</span>';
        }

        $classes = trim('dcg-glightbox ' . $extraClass);
        $plainTitle = html_entity_decode($image['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $glightbox = 'title: ' . $plainTitle;

        return '<a href="' . $image['url'] . '" class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8')
            . '" data-gallery="' . htmlspecialchars($galleryId, ENT_QUOTES, 'UTF-8')
            . '" data-glightbox="' . htmlspecialchars($glightbox, ENT_QUOTES, 'UTF-8') . '">'
            . $imgTag
            . '</a>';
    }

    /**
     * @param array<int, array{url: string, title: string}> $images
     */
    private function renderSwiperLayout(
        string $containerId,
        string $mode,
        array $images,
        string $galleryId,
        string $slideRatio,
        string $navStyle,
        string $dataAttrs
    ): string {
        $modClass = 'dc-gallery--' . $mode;
        $withLightbox = $this->modeSupportsLightbox($mode);
        $html = '<div id="' . $containerId . '" class="dc-gallery ' . htmlspecialchars($modClass, ENT_QUOTES, 'UTF-8') . ' swiper dcg-swiper" style="' . htmlspecialchars($navStyle, ENT_QUOTES, 'UTF-8') . '"' . $dataAttrs . '>';
        $html .= '<div class="swiper-wrapper" id="' . $containerId . '-wrapper">';
        foreach ($images as $image) {
            $imgStyle = $this->buildFitImgStyle($slideRatio, '10px');
            $html .= '<div class="swiper-slide">' . $this->renderImageAnchor($image, $galleryId, 'dcg-slide-link', $imgStyle, $withLightbox) . '</div>';
        }
        $html .= '</div>';
        $html .= $this->renderSwiperNavigationButtons($containerId . '-wrapper');
        $html .= '<div class="swiper-pagination"></div>';
        $html .= '</div>';

        return $html;
    }

    private function renderSwiperNavigationButtons(string $controlsId): string
    {
        $svg = '<svg class="swiper-navigation-icon" width="11" height="20" viewBox="0 0 11 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
            . '<path d="M0.38296 20.0762C0.111788 19.805 0.111788 19.3654 0.38296 19.0942L9.19758 10.2796L0.38296 1.46497C0.111788 1.19379 0.111788 0.754138 0.38296 0.482966C0.654131 0.211794 1.09379 0.211794 1.36496 0.482966L10.4341 9.55214C10.8359 9.9539 10.8359 10.6053 10.4341 11.007L1.36496 20.0762C1.09379 20.3474 0.654131 20.3474 0.38296 20.0762Z" fill="currentColor"></path>'
            . '</svg>';

        return '<div class="swiper-button-prev" tabindex="0" role="button" aria-label="' . htmlspecialchars($this->module->l('Previous'), ENT_QUOTES, 'UTF-8')
            . '" aria-controls="' . htmlspecialchars($controlsId, ENT_QUOTES, 'UTF-8') . '">' . $svg . '</div>'
            . '<div class="swiper-button-next" tabindex="0" role="button" aria-label="' . htmlspecialchars($this->module->l('Next'), ENT_QUOTES, 'UTF-8')
            . '" aria-controls="' . htmlspecialchars($controlsId, ENT_QUOTES, 'UTF-8') . '">' . $svg . '</div>';
    }

    private function normalizeLayoutMode(string $mode): string
    {
        $mode = Tools::strtolower(trim($mode));
        $map = [
            'normal' => 'normal',
            'grid' => 'normal',
            'standard' => 'normal',
            'tiles' => 'tiles',
            'macy' => 'tiles',
            'masonry' => 'tiles',
            'slideshow' => 'slideshow',
            'slider' => 'slideshow',
            'carousel' => 'carousel',
            'coverflow' => 'coverflow',
            'effect_coverflow' => 'coverflow',
            'effect-coverflow' => 'coverflow',
            'cards' => 'cards',
            'effect_cards' => 'cards',
            'effect-cards' => 'cards',
            'thumbs' => 'thumbs',
            'thumbs_gallery' => 'thumbs',
            'thumbs-gallery' => 'thumbs',
        ];

        return $map[$mode] ?? 'normal';
    }

    private function buildFitImgStyle(string $ratio, string $borderRadius = '8px'): string
    {
        $aspect = $this->ratioToAspectCss($ratio);

        return 'display:block;width:100%;aspect-ratio:' . $aspect
            . ';object-fit:cover;object-position:center;border-radius:' . $borderRadius
            . ';margin:0;padding:0;border:0;max-width:100%;height:auto;';
    }

    private function ratioToAspectCss(string $ratio): string
    {
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*:\s*(\d+(?:\.\d+)?)$/', trim($ratio), $m)) {
            return '3/4';
        }

        $w = (float) $m[1];
        $h = (float) $m[2];

        if ($w <= 0 || $h <= 0) {
            return '3/4';
        }

        return rtrim(rtrim(sprintf('%.4F', $w), '0'), '.')
            . '/'
            . rtrim(rtrim(sprintf('%.4F', $h), '0'), '.');
    }

    private function normalizeColumns($value): int
    {
        $n = (int) $value;

        return max(1, min(12, $n < 1 ? 4 : $n));
    }

    private function normalizeSlidesVisible($value): int
    {
        $n = (int) $value;

        return max(1, min(12, $n < 1 ? 3 : $n));
    }

    private function normalizeSpace($value): int
    {
        $n = (int) $value;

        return max(0, min(80, $n < 0 ? 16 : $n));
    }

    private function normalizeColor($value, string $fallback): string
    {
        $color = trim((string) $value);

        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return $color;
        }

        return $fallback;
    }

    private function normalizeOpacity($value, float $fallback): float
    {
        if (!is_numeric($value)) {
            return $fallback;
        }

        $opacity = (float) $value;

        if ($opacity < 0) {
            return 0.0;
        }

        if ($opacity > 1) {
            return 1.0;
        }

        return $opacity;
    }

    private function toBool($value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $normalized = Tools::strtolower((string) $value);

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
