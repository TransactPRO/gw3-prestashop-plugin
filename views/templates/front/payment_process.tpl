{*
* @author       Transact Pro
*}
{extends "$layout"}

{block name="content"}
    
<section id="main">
    
    <header class="page-header">
        <h1>{l s='Credit or Debit Card Payment' mod='transact_pro'}</h1>
    </header>
    
    <section class="page-content card card-block">
    
        {capture name=path}{l s='Credit or Debit Card Payment' mod='transact_pro'}{/capture}

        <div class="transact-pro">
            <div class="clearfix"></div>
            {if isset($action)}
                <form action="{$action|escape:'htmlall':'UTF-8'}" id="transact-pro-form">
            {else}
                <form action="{$link->getModuleLink('transact_pro', 'process', ['order_id' => $orderId], true)|escape:'htmlall':'UTF-8'}"
                        method="post" id="transact-pro-form" class="form-horizontal">
            {/if}
                <input type="hidden" name="order_id" value="{$orderId|intval}" />

                <div class="form-group row">
                    <label for="cardHolder" class="col-xs-4 col-form-label">{l s='Name on Card' mod='transact_pro'}:</label>
                    
                    <div class="col-xs-8">
                        <input type="text" name="card_holder" class="card-holder form-control"
                                   value="{if (isset($input['card_holder']))}{$input['card_holder']|escape:'htmlall':'UTF-8'}{/if}" id="cardHolder"
                                   maxlength="32" placeholder="{l s='Name on Card' mod='transact_pro'}" required />
                    </div>
                </div>
                <div class="form-group row">
                    <label for="cardPan" class="col-xs-4 col-form-label">{l s='Card Number' mod='transact_pro'}:</label>

                    <div class="col-xs-8">
                        <input type="tel" name="card_pan" value="{if (isset($input['card_pan']))}{$input['card_pan']|escape:'htmlall':'UTF-8'}{/if}"
                               class="card-pan only-number form-control" id="cardPan" maxlength="24" placeholder="•••• •••• •••• ••••" required />
                    </div>
                </div>
                <div class="form-group row">
                    <label for="expirationMonth" class="col-xs-4 col-form-label">{l s='Expiration Date' mod='transact_pro'}:</label>
                    
                    <div class="col-xs-4">
                        <select name="expiration_month" class="expiration-month form-control" id="expirationMonth">
                            {for $month=1 to 12}
                                <option value="{$month|intval}"
                                        {if isset($input['expiration_month']) && $input['expiration_month'] == $month}selected{/if}>
                                    {$month|escape:'htmlall':'UTF-8'}
                                </option>
                            {/for}
                        </select>
                    </div>
                    
                    <div class="col-xs-4">
                        <select name="expiration_year" class="expiration-year form-control" id="expirationYear">
                            {for $year=date('Y') to date('Y', strtotime('+ 19 years'))}
                                <option value="{$year|intval}"
                                        {if isset($input['expiration_year']) && $input['expiration_year'] == $year}selected{/if}>
                                    {$year|escape:'htmlall':'UTF-8'}
                                </option>
                            {/for}
                        </select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="cvc" class="col-xs-4 col-form-label">{l s='CVV/CVV2 Code' mod='transact_pro'}:</label>

                    <div class="col-xs-8">
                        <input type="tel" name="cvc" class="cvc only-number form-control"
                               value="{if (isset($input['cvc']))}{$input['cvc']|escape:'htmlall':'UTF-8'}{/if}" id="cvc" autocomplete="off" placeholder="•••" required/>
                    </div>
                </div>
                <div class="form-group row total-amount-block">
                    <label class="col-xs-4 control-label">{l s='Order Total' mod='transact_pro'}:</label>

                    <div class="col-xs-8">
                        {if isset($currency->iso_code)}
                            <strong class="bold form-control-static">{$total|escape:'htmlall':'UTF-8'} {$currency->iso_code|escape:'htmlall':'UTF-8'}</strong>
                        {else}
                            <strong class="bold form-control-static">{$total|escape:'htmlall':'UTF-8'} {$currency['iso_code']|escape:'htmlall':'UTF-8'}</strong>
                        {/if}
                    </div>
                </div>
                
                <div class="form-group row">
                    <div class="col-xs-4">
                    </div>
                    <div class="col-xs-8">
                        <button class="button btn btn-primary"
                                name="make_payment"
                                value="make_payment"
                                type="submit">
                            {l s='Make a payment' mod='transact_pro'}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    
    </section>
    
</section>
    
{/block}