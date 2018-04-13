{*
* @author       Transact Pro
*}
{if $status == 'ok'}
    <p>
        {l s='Your order is complete.' mod='transact_pro'}
    </p>
    <p>
        {l s='We received your payment and your order #' mod='transact_pro'}
        <strong>{$id_order|intval}</strong> {l s='is in preparation.' mod='transact_pro'}
    </p>
{else}
    <p class="warning">
        {l s='We noticed a problem with your order. If you think this is an error, you can contact our' mod='transact_pro'}
        <a href="{$link->getPageLink('contact', true)|escape:'htmlall':'UTF-8'}">{l s='customer support' mod='transact_pro'}</a>.
    </p>
{/if}