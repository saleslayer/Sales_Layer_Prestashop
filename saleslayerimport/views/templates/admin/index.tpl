{**
* NOTICE OF LICENSE
*
* This file is licenced under the Software License Agreement.
* With the purchase or the installation of the software in your application
* you accept the licence agreement.
*
* Sales-layer PIM Plugin for Prestashop
*
*  @author    Sales Layer
*  @copyright 2019 Sales Layer
*  @license  License: GPLv3  License URI: https://www.gnu.org/licenses/gpl-3.0.html
*}

{* Load Stylesheet Files *}{strip}
  <link rel="stylesheet" href="{$SLY_ASSETS_PATH|escape:'htmlall':'UTF-8'}views/css/slyrimport.css"/>
  <div class="container mar-top-btt-40">
    <div class="row">
      <div class="col-lg-4 col-md-4 col-sm-12 pad-10">
        <img src="{$SLY_LOGOS_PATH|escape:'htmlall':'UTF-8'}logob_{$COMPANY_TYPE|escape:'htmlall':'UTF-8'}.png"
             height="65px;" alt="logo sales layer" class="max-h-50p">
      </div>
      <div class="col-lg-6 col-md-4 col-sm-12 pad-10">
        <a href="{$link_all_conectors|escape:'htmlall':'UTF-8'}" class="btn btn-success width-150 mar-10"><i
            class="fa fa-eye" aria-hidden="true"></i> View connectors</a>
      </div>
      <div class="row">
        <div class="col-lg-5 col-md-4 col-xs-12 pad-10" id="slh1selector"></div>
        <div class="col-lg-5 col-md-4 col-xs-12 pad-10 forinfo">
          <span class="pull-left" id="sllisted"></span><span class="pull-left" id="slwarnings"></span><span
            class="pull-left" id="slerrors"></span>
        </div>
      </div>
    </div>
    {*{if isset($SLY_HAS_CONNECTORS)}*}
    {*<form class="row slyr-import-ajax-form form-group" action="" method="post" autocomplete="off" role="form" class="slyr-import-form-login">
        <div class="col-md-9 slyr-form-field-block mar-top-btt-10">
        <input type="hidden" name="saleslayerimport[action]" value="viewconn" />*}
    {*</div>
  </form>*}
    {*{/if}*}

    {if isset($SLY_HAS_ERRORS)}
      <div class="sy-alert sy-danger mar-top-btt-40 text-center">
        <p>Error! Wrong credentials.</p>
      </div>
    {/if}

    <div class="row mar-top-btt-40" id="slyr-import-module-block">
      <h1 class="mar-top-btt-40">Add New Connector</h1>
      <form action="" method="post" autocomplete="off" role="form"
            class="col-md-6 mar-top-btt-40 slyr-import-form-login form-group">
        <input type="hidden" name="saleslayerimport[action]" value="connect"/>
        <div class="slyr-form-field-block form-group">
          <label for="saleslayerimport_api_client">Connector ID:</label>
          <input type="text" id="saleslayerimport_api_client" name="api_client"
                 value="{$api_client|escape:'htmlall':'UTF-8'}"/>
        </div>
        <div class="slyr-form-field-block form-group">
          <label for="saleslayerimport_api_key">Private Key:</label>
          <input type="text" id="saleslayerimport_api_key" name="api_key"
                 value="{$api_key|escape:'htmlall':'UTF-8'}"/>
        </div>
        <div class="slyr-form-field-block mar-top-btt-40 form-group">
          <button type="submit" class="btn btn-info width-150" name="addnewconector"><i
              class="fa fa-refresh text-left" aria-hidden="true"></i> Save Connector
          </button>
        </div>
      </form>
    </div>
  </div>
{/strip}
