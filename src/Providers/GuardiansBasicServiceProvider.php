<?php

namespace GuardiansBasic\Providers;

use Plenty\Modules\Webshop\ItemSearch\Helpers\ResultFieldTemplate;
use Plenty\Plugin\ServiceProvider;
use Plenty\Plugin\Events\Dispatcher;
use Plenty\Plugin\Templates\Twig;
use IO\Helper\TemplateContainer;
use IO\Services\TemplateService;
use IO\Extensions\Functions\Partial;
use Plenty\Plugin\ConfigRepository;
use GuardiansBasic\Extensions\Base_Extension;
use GuardiansBasic\Helper\SeoPage;
use Plenty\Modules\Webshop\Consent\Contracts\ConsentRepositoryContract;

/**
 * Class GuardiansBasicServiceProvider
 * @package GuardiansBasic\Providers
 */
class GuardiansBasicServiceProvider extends ServiceProvider
{
    const PRIORITY = 0;

    public function register()
    {
        $this->getApplication()->register(\GuardiansBasic\Providers\GuardiansBasicRouteServiceProvider::class);
        $consentRepository = pluginApp(ConsentRepositoryContract::class);
        $consentRepository->registerConsent(
            'PoP-UP Anzeige',
            'PoP-UP Anzeige',
            [
                'description' => '-',
                'provider' => 'Outlet46.de GmbH',
                'lifespan' => 'Session',
                'policyUrl' => 'https://www.outlet46.de/datenschutz/',
                'group' => 'necessary',
                'necessary' => true,
                'isOptOut' => false,
                'cookieNames' => ['showModal', 'setCookie']
            ]
        );
        $consentRepository->registerConsent(
            'getblue Retargeting',
            'getblue Retargeting',
            [
                'description' => '-',
                'provider' => 'getblue.io',
                'lifespan' => '100 Tage',
                'policyUrl' => 'https://www.outlet46.de/datenschutz/',
                'group' => 'marketing',
                'necessary' => false,
                'isOptOut' => false,
                'cookieNames' => ['blueID', 'ckid', 'blueULC']
            ]
        );
        $consentRepository->registerConsent(
            '42ads Retargeting',
            '42ads Retargeting',
            [
                'description' => '-',
                'provider' => '42ads.io',
                'lifespan' => '100 Tage',
                'policyUrl' => 'https://www.outlet46.de/datenschutz/',
                'group' => 'marketing',
                'necessary' => false,
                'isOptOut' => false,
                'cookieNames' => ['uid']
            ]
        );
        $consentRepository->registerConsent(
            'order',
            'order',
            [
                'description' => '-',
                'provider' => 'Outlet46.de GmbH',
                'lifespan' => '100 Tage',
                'policyUrl' => 'https://www.outlet46.de/datenschutz/',
                'group' => 'necessary',
                'necessary' => true,
                'isOptOut' => false,
                'cookieNames' => ['at_gd']
            ]
        );
        $consentRepository->registerConsent(
            'Language, Currency',
            'Language, Currency',
            [
                'description' => '-',
                'provider' => 'Outlet46.de GmbH',
                'lifespan' => 'Session',
                'policyUrl' => 'https://www.outlet46.de/datenschutz/',
                'group' => 'necessary',
                'necessary' => true,
                'isOptOut' => false,
                'cookieNames' => ['language', 'ledgerCurrency', 'apay-session-set', 'session-id', 'session-id-apay', 'session-id-time', 'session-token', 'JSESSIONID']
            ]
        );
                
    }
    public function boot(Twig $twig, Dispatcher $dispatcher, ConfigRepository $config)
    {
        $twig->addExtension(Base_Extension::class);
        // Apply the same approved page size to the native Ceres listing query.
        foreach (['IO.ctx.category.item', 'IO.ctx.tags', 'IO.ctx.search'] as $event) {
            $dispatcher->listen($event, function () {
                TemplateService::$currentTemplateData['itemsPerPage'] = Base_Extension::SHOPBUILDER_ITEMS_PER_PAGE;
                TemplateService::$currentTemplateData['page'] = SeoPage::positiveInteger(TemplateService::$currentTemplateData['page'] ?? 1) ?? 1;
                if (!is_string(TemplateService::$currentTemplateData['sorting'] ?? '')) {
                    TemplateService::$currentTemplateData['sorting'] = '';
                }
            }, self::PRIORITY + 1);
        }
        $enabledOverrides = explode(", ", $config->get("GuardiansBasic.templates.override"));
        $overrideAll = in_array("all", $enabledOverrides);
        // Override partials
        $dispatcher->listen('IO.init.templates', function (Partial $partial) use ($enabledOverrides, $overrideAll)
        {
            $partial->set('head', 'Ceres::PageDesign.Partials.Head');
            $partial->set('header', 'Ceres::PageDesign.Partials.Header.Header');
            $partial->set('page-design', 'Ceres::PageDesign.PageDesign');
            $partial->set('page-metadata', 'GuardiansBasic::PageDesign.Partials.PageMetadata');
            $partial->set('footer', 'Ceres::PageDesign.Partials.Footer');

            if (in_array("head", $enabledOverrides) || $overrideAll)
            {
                $partial->set('head', 'GuardiansBasic::PageDesign.Partials.Head');
            }

            if (in_array("header", $enabledOverrides) || $overrideAll)
            {
                $partial->set('header', 'GuardiansBasic::PageDesign.Partials.Header.Header');
            }

            if (in_array("page_design", $enabledOverrides) || $overrideAll)
            {
                $partial->set('page-design', 'GuardiansBasic::PageDesign.PageDesign');
            }

            if (in_array("footer", $enabledOverrides) || $overrideAll)
            {
                $partial->set('footer', 'GuardiansBasic::PageDesign.Partials.Footer');
            }

            return false;
        }, self::PRIORITY);

        // Keep registration order and configuration keys in one place.
        $templateOverrides = [
            'homepage' => ['IO.tpl.home', 'GuardiansBasic::Homepage.Homepage'],
            'category_content' => ['IO.tpl.category.content', 'GuardiansBasic::Category.Content.CategoryContent'],
            'category_item' => ['IO.tpl.category.item', 'GuardiansBasic::Category.Item.CategoryItem'],
            'basket' => ['IO.tpl.basket', 'GuardiansBasic::Basket.Basket'],
            'checkout' => ['IO.tpl.checkout', 'GuardiansBasic::Checkout.CheckoutView'],
            'order_confirmation' => ['IO.tpl.confirmation', 'GuardiansBasic::Checkout.OrderConfirmation'],
            'login' => ['IO.tpl.login', 'GuardiansBasic::Customer.Login'],
            'register' => ['IO.tpl.register', 'GuardiansBasic::Customer.Register'],
            'item' => ['IO.tpl.item', 'GuardiansBasic::Item.SingleItem'],
            'category_view' => ['IO.tpl.search', 'GuardiansBasic::Category.Item.CategoryItem'],
            'my_account' => ['IO.tpl.my-account', 'GuardiansBasic::MyAccount.MyAccount'],
            'cancellation_rights' => ['IO.tpl.cancellation-rights', 'Ceres::StaticPages.CancellationRights'],
            'legal_disclosure' => ['IO.tpl.legal-disclosure', 'Ceres::StaticPages.LegalDisclosure'],
            'privacy_policy' => ['IO.tpl.privacy-policy', 'Ceres::StaticPages.PrivacyPolicy'],
            'terms_conditions' => ['IO.tpl.terms-conditions', 'Ceres::StaticPages.TermsAndConditions'],
            'item_not_found' => ['IO.tpl.item-not-found', 'GuardiansBasic::StaticPages.ItemNotFound'],
            'page_not_found' => ['IO.tpl.page-not-found', 'GuardiansBasic::StaticPages.PageNotFound'],
        ];
        foreach ($templateOverrides as $key => [$event, $template]) {
            if ($overrideAll || in_array($key, $enabledOverrides)) {
                $dispatcher->listen($event, function (TemplateContainer $container) use ($template) {
                    $container->setTemplate($template);
                    return false;
                }, self::PRIORITY);
            }
        }
        $resultFieldTemplate = pluginApp(ResultFieldTemplate::class);
        $resultFieldTemplate->setTemplates([
            ResultFieldTemplate::TEMPLATE_LIST_ITEM     => 'GuardiansBasic::ResultFields.ListItem',
            ResultFieldTemplate::TEMPLATE_SINGLE_ITEM   => 'GuardiansBasic::ResultFields.SingleItem',
            ResultFieldTemplate::TEMPLATE_BASKET_ITEM   => 'GuardiansBasic::ResultFields.BasketItem',
            ResultFieldTemplate::TEMPLATE_AUTOCOMPLETE_ITEM_LIST => 'GuardiansBasic::ResultFields.AutoCompleteListItem',
            ResultFieldTemplate::TEMPLATE_CATEGORY_TREE => 'GuardiansBasic::ResultFields.CategoryTree',
            ResultFieldTemplate::TEMPLATE_VARIATION_ATTRIBUTE_MAP => 'Ceres::ResultFields.VariationAttributeMap'
        ]);
    }
}



