<?php

class WooCartFrontendFestiPlugin extends WooCartFestiPlugin
{
    protected $settings = array();
    protected $localizeVars = array();
    protected $customizeCssFilesSpool = array();
    protected $customizeCssTemplatesSpool = array();
    
    public function onInit()
    {
        
        $this->oInitWpmlTraslator();

        $this->settings = $this->getSettings();

        $this->_onInitPluginCookie();
        
        $woocommerce = $this->getWoocommerceInstance();
        
        $this->addActionListener('wp', 'onCalculateCartTotalsAction');

        $this->addActionListener('wp_enqueue_scripts', 'onInitJsAction');

        $this->addActionListener('wp_print_styles', 'onInitCssAction');

        $this->addActionListener(
            'wp_ajax_nopriv_remove_product',
            'onRemoveCartProductAction'
        );

        $this->addActionListener(
            'woocommerce_add_to_cart',
            'showPopupContainerAction'
        );

        $this->addActionListener(
            'wp_ajax_remove_product',
            'onRemoveCartProductAction'
        );
       
        $this->addFilterListener(
            $woocommerce->getHookAddToCartFragments(),
            'onDisplayCartFilter'
        );

        $this->addShortcodeListener(
            'WooCommerceWooCartPro',
            'onDisplayShortCode'
        );

        $this->addShortcodeListener(
            'WoocommerceWooCartPro',
            'onDisplayShortCode'
        );

        $this->addActionListener(
            'wp_ajax_update_total_price',
            'onAjaxGetTotalPrice'
        );

        $this->addActionListener(
            'wp_ajax_nopriv_update_total_price',
            'onAjaxGetTotalPrice'
        );

        $this->addActionListener(
            'wp_ajax_do_display_cart_in_window_after_icon_loaded',
            'onAjaxGetCartToDisplayInWindowAfterIconLoaded'
        );
        
        $this->addActionListener(
            'wp_ajax_nopriv_do_display_cart_in_window_after_icon_loaded',
            'onAjaxGetCartToDisplayInWindowAfterIconLoaded'
        );
        
        // FIXME: Wrong action type, for example
        // [nav_menu_item] call to this action
        $this->addActionListener('pre_get_posts', 'onCartAppendAction');

        $this->appendHiddenDropdownList($this->settings);

        $this->addActionListener(
            'wp',
            'onAppendCssForCartCustomizeAction'
        );

        $this->addActionListener('wp_head', 'onAddCartProductsToHead');

        $this->appendHiddenPopupContainer($this->settings);
        
        $this->_onInitWooProductBundlesPlugin();
    } // end onInit

    public function onCartAppendAction($query)
    {
        if (!$this->_isPageChosenToDisplayCart($query)) {
            return false;
        }

        $this->appendToMenu($this->settings);

        $this->appendToWindow($this->settings);
    }

    private function _isPageChosenToDisplayCart($query)
    {
        if ($this->isDisplayOnAllPagesOptionOn()) {
            return true;
        }

        $currentPage = $this->_getCurrentPageID($query);

        if ($this->isBlogPage($query)) {
            $currentPage = get_option('page_for_posts');
        }

        if ($this->_isFrontendPage($query)) {
            $currentPage = get_option('page_on_front');
        }

        if ($this->_isShopPage()) {
            $currentPage = wc_get_page_id('shop');
        }

        if (!$currentPage) {
            return false;
        }

        if ($this->_isProductPostType($currentPage)) {
            return true;
        }

        return $this->isSelectedPageForCart($currentPage);
    }

    private function _getCurrentPageID($query)
    {
        $id = get_the_ID();

        if (!$id) {
            $id = $this->_getPageIdFromQuery($query);
        }

        return $id;
    }

    private function _getPageIdFromQuery($query)
    {
        if (!$this->_isPageIdExistInQuery($query)) {
            return false;
        }

        return $query->queried_object->ID;
    }

    private function _isPageIdExistInQuery($query)
    {
        return is_object($query) && isset($query->queried_object) &&
               isset($query->queried_object->ID);
    }

    private function _isFrontendPage($query)
    {
        return $query->get('page_id') == get_option('page_on_front') ||
               is_front_page();
    } // end _isFrontendPage

    private function _isShopPage()
    {
        // FIXME: Remove @
        return @is_shop();
    } // end _isShopPage

    private function _isProductPostType($id)
    {
        return get_post_type($id) == 'product';
    }

    private function isBlogPage($query)
    {
        return $query->is_home() && $query->is_main_query();
    }

    private function _isCustomCssFolderExists()
    {
        $path = $this->getCustomCssPath();

        return file_exists($path);
    } // end _isCustomCssFolderExists

    public function onAppendCssForCartCustomizeAction()
    {
        $fileNamesList = $this->getFileNamesListOfCustomizeCart();

        $isCustomCssFolderExists = $this->_isCustomCssFolderExists();

        foreach ($fileNamesList as $name) {
            $this->addFileNameToSpool($isCustomCssFolderExists, $name);
        }
        if ($this->customizeCssFilesSpool) {
            $this->addActionListener(
                'wp_print_styles',
                'onInitCustomCssAction'
            );
        }

        if ($this->customizeCssTemplatesSpool) {
            $this->addActionListener('wp_head', 'addCssToHeaderAction');
        }
    } // end onAppendCssForCartCustomizeAction

    protected function addFileNameToSpool($isCustomCssFolderExists, $name)
    {
        if ($isCustomCssFolderExists && $this->_isExistsCustomCssFile($name)) {
            $this->customizeCssFilesSpool[] = $name;
            return true;
        }

        $this->customizeCssTemplatesSpool[] = $name;
    } // end addFileNameToSpool

    private function _isExistsCustomCssFile($fileName)
    {
        $file = $this->getCustomCssPath($fileName.'.css');
        return file_exists($file);
    } // end _isExistsCustomCssFile

    public function onInitCustomCssAction()
    {
        $version = time();

        $spool = $this->customizeCssFilesSpool;

        foreach ($spool as $fileName) {
            $this->onEnqueueCssFileAction(
                'festi-cart-'.str_replace('_', '-', $fileName),
                'customize/'.$fileName.'.css?'.time(),
                'festi-cart-styles',
                $version
            );
        }
    } // end onInitCustomCssAction

    public function isActiveWoocommerceAdditionalFeesPlugin()
    {
        return $this->isPluginActive('woocommerce-additional-fees/woocommerce_additional_fees_plugin.php');
    }


    public function onCalculateCartTotalsAction()
    {
        $woocommerce = $this->getWoocommerceInstance();
        
        if (!$this->isActiveWoocommerceAdditionalFeesPlugin()) {
            $woocommerce->getCalculateTotals();
        }

    } // end onCalculateCartTotalsAction

    public function showPopupContainerAction()
    {
        $this->addActionListener(
            'wp_head',
            'appendCallScriptPopupContainerAction'
        );
    } // end showPopupContainerAction

    public function appendCallScriptPopupContainerAction()
    {
        $vars = array(
            'settings' => $this->settings,
        );
        echo $this->fetch('popup_call_script.phtml', $vars);
    } // end appendCallScriptPopupContainerAction

    public function getPluginCssUrl($fileName)
    {
        return $this->_pluginCssUrl.'frontend/'.$fileName;
    } // end getPluginCssUrl

    public function getPluginJsUrl($fileName)
    {
        return $this->_pluginJsUrl.'frontend/'.$fileName;
    } // end getPluginJsUrl

    public function getPluginTemplatePath($fileName)
    {
        return $this->_pluginTemplatePath.'frontend/'.$fileName;
    } // end getPluginTemplatePath

    public function onInitJsAction()
    {
        $settings = $this->getSettings();

        $this->onEnqueueJsFileAction('jquery');
        
         $this->onEnqueueJsFileAction(
            'festi-jquery-ui-spinner',
            'vendor/jquery-ui.spinner.min.js',
            'jquery',
            $this->_version,
            true
        );
        $this->onEnqueueJsFileAction(
            'festi-cart-general',
            'general.js',
            'festi-cart-popup',
            $this->_version,
            true
        );
        $this->onEnqueueJsFileAction(
            'festi-cart-popup',
            'popup.js',
            'jquery',
            $this->_version,
            true
        );
        
       

        $this->localizeVars = array(
            'ajaxurl'        => admin_url('admin-ajax.php'),
            'imagesUrl'      => $this->getPluginImagesUrl(''),
            'isMobile'       => wp_is_mobile(),
            'isEnabledPopUp' => $this->_isEnablePopUpWindow($settings),
            'isActiveTableGridPlugin' => $this->_isActiveWooVariationsTableGridPlugin()
        );

        if ($this->_isEnableDropdownList($settings)) {
            $optionName = 'cartDropdownListAligment';
            $this->localizeVars['productListAligment'] = $settings[$optionName];
        }

        wp_localize_script(
            'festi-cart-general',
            'fesiWooCart',
            $this->localizeVars
        );

        $this->addActionListener(
            'wp_footer',
            'appendAdditionallocalizeScriptAction'
        );

        $this->appendCartToCustomPositionInMenu($settings);
    } // end onInitJsAction

    public function appendAdditionallocalizeScriptAction()
    {
        $args = array(
            'vars' => json_encode($this->localizeVars)
        );

        echo $this->fetch('additional_localize_script.phtml', $args);
    } // end appendAdditionallocalizeScript

    public function appendCartToCustomPositionInMenu($settings)
    {
        if (!$this->isEnableDisplayingCartInMenu($settings, false)) {
            return false;
        }

        $this->onEnqueueJsFileAction(
            'festi-cart-position-in-menu',
            'cart_in_menu.js',
            'jquery',
            $this->_version,
            true
        );


        $vars = array(
            'menu'     => '',
            'settings' => $settings,
        );

        $cartInMenu = $this->fetch('menu_item.phtml', $vars);

        $vars = array(
            'cartContent' => $cartInMenu
        );

        wp_localize_script(
            'jquery',
            'fesiWooCartInMenu',
            $vars
        );
    } // end appendCartToCustomPositionInMenu

    public function onRemoveCartProductAction()
    {
        if ($this->_hasDeleteItemInRequest()) {
            $woocommerce = $this->getWoocommerceInstance();
            $item = $_POST['deleteItem'];
            $woocommerce->setCartQuantity($item, 0);

            echo $woocommerce->getCartContentsCount();
        }

        wp_die();
    } // end onRemoveCartProductAction

    private function _hasDeleteItemInRequest()
    {
        return array_key_exists('deleteItem', $_POST) &&
               !empty($_POST['deleteItem']);
    } // end _hasDeleteItemInRequest

    public function onInitCssAction()
    {
        $this->onEnqueueCssFileAction(
            'festi-cart-styles',
            'style.css',
            array(),
            $this->_version
        );
        
        $this->onEnqueueCssFileAction(
            'festi-jquery-ui-spinner',
            'vendor/ui-spinner/jquery-ui.spinner.min.css',
            array(),
            $this->_version
        );

        if (!wp_is_mobile()) {
            return false;
        }

        $this->onEnqueueCssFileAction(
            'festi-cart-responsive',
            'responsive.css',
            'festi-cart-styles',
            $this->_version
        );
    } // end onInitCssAction

    private function _onInitPluginCookie()
    {
        $this->addActionListener('wp_enqueue_scripts', 'onClearStorageAction');
    } // end _onInitPluginCookie

    public function getPluginCookie()
    {
        $value = array();

        $value = $this->getOptions('cookie');

        return $value[0];
    } // end getPluginCookie

    private function _setCookieForWoocommerceCartHash($name, $value, $time = 0)
    {
        setcookie(
            $name,
            $value,
            $time,
            COOKIEPATH,
            COOKIE_DOMAIN
        );
    } // end _setCookieForWoocommerceCartHash

    public function fetchDropdownListContent()
    {
        $settings = $this->getSettings();

        $vars = array(
            'woocommerce' => $this->getWoocommerceInstance(),
            'settings'    => $settings
        );

        return $this->fetch('dropdown_list_content.phtml', $vars);
    } // end fetchDropdownListContent

    private function _hasValueInCookieArray($cookieName)
    {
        return isset($_COOKIE[$cookieName]) &&
               !empty($_COOKIE[$cookieName]);
    } // end _hasValueInCookieArray

    private function _isChangedCookieValue($value)
    {
        return $_COOKIE['festi_cart_for_woocommerce_storage'] != $value;
    } // end _isChangedCookieValue

    public function onClearStorageAction()
    {
        $this->onEnqueueJsFileAction(
            'festi-cart-clear-storage',
            'clear_storage.js',
            'jquery',
            true
        );
    } // end onHeadAction

    public function appendToMenu($options)
    {
        if (!$this->isEnableDisplayingCartInMenu($options)) {
            return false;
        }

        $currentValue = $options['menuList'];

        foreach ($currentValue as $menuSlug) {
            add_filter(
                'wp_nav_menu_'.$menuSlug.'_items',
                array(&$this, 'onMenuItemsFilter'),
                10,
                2
            );
        }

        return true;
    } // end appendToMenu

    public function appendToWindow($options)
    {
        if (!$this->_isEnableDisplayingCartInWindow($options)) {
            return false;
        }

        $this->addActionListener(
            'wp_footer',
            'onDisplayCartInBrowserWindowAction'
        );
    } // end appendToWindow

    public function onDisplayCartInBrowserWindowAction()
    {
        $vars = array(
            'settings' => $this->settings,
        );

        echo $this->fetch('browser_window_cart.phtml', $vars);
    } // end onDisplayCartInBrowserWindowAction

    private function _isEnableDisplayingCartInWindow($options)
    {
        return array_key_exists('windowCart', $options) &&
               !empty($options['windowCart']);
    } // end _isEnableDisplayingCartInWindow

    public function appendHiddenDropdownList($options)
    {
        if (!$this->_isEnableDropdownList($options)) {
            return false;
        }

        $this->addActionListener(
            'wp_footer',
            'onDisplayDropdownListAction'
        );

        $this->appendArrowToDropdownList($options);
    } // end appendHiddenDropdownList

    public function appendHiddenPopupContainer($options)
    {
        if (!$this->_isEnablePopUpWindow($options)) {
            return false;
        }

        $this->addActionListener(
            'wp_footer',
            'onDisplayPopupContainerAction'
        );
    } // end appendHiddenPopupContainer

    private function _isEnablePopUpWindow($options)
    {
        return array_key_exists('popup', $options) &&
               !empty($options['popup']);
    } // end _isEnablePopUpWindow

    public function onDisplayDropdownListAction()
    {
        $content = $this->fetchDropdownListContent();

        $vars = array(
            'content' => $content,
        );

        echo $this->fetch('dropdown_list.phtml', $vars);
    } // end onDisplayDropdownListAction

    public function onDisplayPopupContainerAction()
    {
        $settings = $this->getSettings();
        
        $vars = array(
            'woocommerce' => $this->getWoocommerceInstance(),
            'settings'    => $settings
        );
        
        $content = $this->fetch('popup_content.phtml', $vars);

        $vars['content'] = $content;

        echo $this->fetch('popup_container.phtml', $vars);
    } // end onDisplayPopupContainerAction

    private function _isEnableDropdownList($options)
    {
        return $options['dropdownAction'] != 'disable';
    } // end _isEnableDropdownList

    public function appendArrowToDropdownList($options)
    {
        if (!$this->_isEnableDisplayingArrowOnDropdownList($options)) {
            return false;
        }

        $this->addActionListener(
            'wp_footer',
            'onDisplayArrowOnDropdownListAction'
        );
    } // end appendArrowToDropdownList

    public function onDisplayArrowOnDropdownListAction()
    {
        $vars = array(
            'settings' => $this->settings,
        );

        echo $this->fetch('dropdown_arrow.phtml', $vars);
    } // end onDisplayArrowOnDropdownListAction

    private function _isEnableDisplayingArrowOnDropdownList($options)
    {
        return array_key_exists('borderArrow', $options) &&
               !empty($options['borderArrow']);
    } // end _isEnableDisplayingArrowOnDropdownList

    public function addCssToHeaderAction()
    {
        $vars = array(
            'settings'    => $this->settings,
            'woocommerce' => $this->getWoocommerceInstance()
        );

        $spool = $this->customizeCssTemplatesSpool;

        foreach ($spool as $fileName) {
            echo $this->fetch('customize/'.$fileName.'.phtml', $vars);
        }
    } // end addCssToHeaderAction

    public function onMenuItemsFilter($nav, $args)
    {
        $vars = array(
            'menu'     => $nav,
            'settings' => $this->settings,
        );

        return $this->fetch('menu_item.phtml', $vars);
    } // end onMenuItemsFilter

    public function isEnableDisplayingCartInMenu($options, $menuList = true)
    {
        $result = array_key_exists('displayMenu', $options) &&
                  !empty($options['displayMenu']);

        if (!$result || ($result && !$menuList)) {
            return $result;
        }

        return !empty($options['menuList']);
    } // end isEnableDisplayingCartInMenu

    public function onDisplayShortCode($attr = array())
    {
        $folder = 'shortcode/';

        if (!$attr) {
            return $this->fetch($folder.'shortcode.phtml');
        }

        $result = $this->_hasOptionInShortcodeAttributes(
            'widgettextformenu',
            $attr
        );

        if ($result) {
            return $this->fetch($folder.'widget_text_for_menu.phtml');
        }
    } // end onDisplayShortCode

    protected function displayCrossSellProducts()
    {
        echo $this->fetch('cross_sell_products.phtml');
    }

    private function _hasOptionInShortcodeAttributes($oprionName, $attr)
    {
        return array_key_exists($oprionName, $attr) &&
               !empty($attr[$oprionName]);
    } // end _hasOptionInShortcodeAttributes

    public function fetchCart($class = '', $template = 'cart.phtml')
    {
        $settings = $this->getSettings();

        $vars = array(
            'woocommerce' => $this->getWoocommerceInstance(),
            'settings'    => $settings
        );

        if ($class) {
            $vars['additionaClass'] = $class;
        }

        return $this->fetch($template, $vars);
    } // end fetchCart

    public function onDisplayCartFilter($cssSelectors)
    {
        $classes = array(
            'festi-cart-widget',
            'festi-cart-shortcode',
            'festi-cart-menu',
            'festi-cart-window'
        );

        foreach ($classes as $value) {
            $class = $value;

            $content = $this->fetchCart($class);
            $cssSelectors['.festi-cart.'.$value] = $content;
        }

        $content = $this->fetchCart(false, 'dropdown_list_content.phtml');

        $selectorName = '.festi-cart-products-content';

        $cssSelectors[$selectorName] = $content;

        $content = $this->fetchCart(false, 'widget_products_list.phtml');

        $selectorName = '.festi-cart-widget-products-content';

        $cssSelectors[$selectorName] = $content;

        $content = $this->fetchCart(false, 'popup_content.phtml');

        $selectorName = '.festi-cart-pop-up-products-content';

        $cssSelectors[$selectorName] = $content;

        return $cssSelectors;
    } // end onDisplayCartFilter

    public function updateCacheFile($fileName, $values)
    {

        if (!is_writable($this->_pluginCachePath)) {
            return false;
        }

        $content = "<?php return '".$values."';";

        $filePath = $this->getPluginCachePath($fileName);

        file_put_contents($filePath, $content, LOCK_EX);
    } //end updateCacheFile

    /**
     * Add javascript variable festiCartProductsItems
     * with products list to head.
     *
     * @action wp_head
     */
    public function onAddCartProductsToHead()
    {
        $woocommerce = $this->getWoocommerceInstance();
        
        $items = array();

        foreach ($woocommerce->getCart()->cart_contents as $cartProduct) {
            $items[$cartProduct['product_id']] = array(
                'id'       => $cartProduct['product_id'],
                'quantity' => $cartProduct['quantity'],
                'quantity' => $cartProduct['quantity'],
                'total'    => $cartProduct['line_total'],
                'quantity' => $cartProduct['quantity'],
                'name'     => $woocommerce->getProductTitle($cartProduct)
            );
        }

        $vars = array(
            'cartProductItems' => $items
        );

        echo $this->fetch('cart_products_script.phtml', $vars);
    } // end onAddCartProductsToHead
    
    public function onAjaxGetCartToDisplayInWindowAfterIconLoaded()
    {
        $response = array(
            'body' => $this->fetchCart()
        );
        
        wp_send_json($response);
    } // end onAjaxGetCartToDisplayInWindowAfterIconLoaded

    public function onAjaxGetTotalPrice()
    {
        $validAjaxData = $this->_isValidReceivedAjaxData();

        if ($validAjaxData) {
            $args = array(
                'quantity' => $_POST['quantity'],
                'itemKey' => $_POST['itemKey']
            );
            $result = $this->_doAjaxUpdateTotalPrice(
                $args, 
                $this->getWoocommerceInstance(),
                $this->getSettings()
            );
            echo $result;
        } else {
            $message = $this->doPrepareErrorMessage(
                "Is not complete validate received ajax data"
            );
            echo $message;
        }
        wp_die();
    } // end onAjaxGetTotalPrice


    private function _isValidReceivedAjaxData()
    {
        return (!empty($_POST['quantity']) &&
                !empty($_POST['itemKey']) &&
                array_key_exists($_POST['itemKey'], WC()->cart->get_cart()));
    } // end _isValidReceivedAjaxData

    private function _doAjaxUpdateTotalPrice(
        $validAjaxData,
        $woocommerce,
        $settings
    )
    {
        $cart = $woocommerce->getCartContents();
        $itemKey = $validAjaxData['itemKey'];
        $quantityCurrentProduct = $validAjaxData['quantity'];
        $dataProduct = $cart[$itemKey];

        $quantity = apply_filters(
            'woocommerce_stock_amount_cart_item',
            apply_filters(
                'woocommerce_stock_amount', $quantityCurrentProduct
            ),
            $itemKey
        );

        $passedValidation = apply_filters(
            'woocommerce_update_cart_validation',
            true,
            $itemKey,
            $dataProduct,
            $quantityCurrentProduct
        );

        if ($passedValidation) {
            $woocommerce->setCartQuantity($itemKey, $quantity, false);
        }
        $woocommerce->getCalculateTotals();
        $result = array(
            "totalPrice" => $woocommerce->getCartSubtotal(),
            "totalItems" => $woocommerce->getCartContentsCount(),
            "productListTotalText" => $settings['productListTotalText'],
            "textAfterQuantity" => $settings['textAfterQuantity'],
            "textAfterQuantityPlural" => $settings['textAfterQuantityPlural']
        );

        return json_encode($result);
    } // end doAjaxUpdateTotalPrice

    public function doPrepareErrorMessage($errMsg)
    {
        $message = __(
            $errMsg,
            $this->_languageDomain
        );
        return json_encode(
            array(
                'error' => array(
                    'message' => $message
                )
            )
        );
    }

    private function _isActiveWooVariationsTableGridPlugin()
    {
        return $this->isPluginActive('woo-variations-table/woo-variations-table.php');
    } // end _isActiveWooVariationsTableGridPlugin
    
    private function _isWooProductBundlesPluginActive()
    {
        return $this->isPluginActive(
            'woocommerce-product-bundles/woocommerce-product-bundles.php'
        );
    } // end _isWooProductBundlesPluginActive
    
    private function _onInitWooProductBundlesPlugin()
    {
        if (!$this->_isWooProductBundlesPluginActive()) {
            return true;
        }
        
        if (!class_exists('WooCartProductBundlesFacade')) {
            require_once __DIR__.'/compatibility/WooCartProductBundlesFacade.php';
        }    
        
        $this->addFilterListener(
            WOOCARTPRO_FILTER_POPUP_COUNT_ITEM_PRODUCT,
            'onDisplayItemCartCountByPopup',
            10,
            2
        );
        
        $this->addFilterListener(
            WOOCARTPRO_FILTER_POPUP_REMOVE_ITEM_PRODUCT,
            'onDisplayItemCartIconRemoveByPopup',
            10,
            2
        );
        
        $this->addFilterListener(
            WOOCARTPRO_FILTER_POPUP_HIDE_ITEM_PRODUCT,
            'onHideBundleItemProduct',
            10,
            2
        );
        
        return true;
    } // end _onInitWooProductBundlesPlugin

    public function onDisplayItemCartCountByPopup($content, $vars)
    {
        $wooBundleFacade = new WooCartProductBundlesFacade();
        
        if ($wooBundleFacade->isBundledCartItem($vars['cart_item'])) {
             return $this->fetch(
                'popup_content_product_bundle_title.phtml',
                $vars
             );
        }   
        return $content;
    } // end onDisplayItemCartCountByPopup

    public function onDisplayItemCartIconRemoveByPopup($content, $cartItem)
    {
        $wooBundleFacade = new WooCartProductBundlesFacade();
        
        if ($wooBundleFacade->isBundledCartItem($cartItem)) {
            return false;
        }   
        
        return $content;
    } // end onDisplayItemCartIconRemoveByPopup
    
    public function onHideBundleItemProduct($isHide, $cartItem)
    {
        $wooBundleFacade = new WooCartProductBundlesFacade();
        
        if ($wooBundleFacade->isBundledCartItem($cartItem)) {
            return true;
        }   
        
        return $isHide;
    } // end onHideBundleItemProduct
}
