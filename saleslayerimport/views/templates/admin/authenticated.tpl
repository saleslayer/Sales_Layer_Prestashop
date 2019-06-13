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
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css"/>
  <link rel="stylesheet" href="{$SLY_ASSETS_PATH|escape:'htmlall':'UTF-8'}views/css/slyrimport.css"/>
  <div class="container" id="mymodule_wrapper" data-token="{$token|escape:'htmlall':'UTF-8'}">
    <input type="hidden" id="ajax_link_sl" value="{$ajax_link|escape:'htmlall':'UTF-8'}"/>
    <input type="hidden" id="allelements" value="0"/>
    <div class="row mar-top-btt-40 slyr-import-ajax-form slyr-import-form-login">
      <div class="col-md-4" id="logo">
        <img src="{$SLY_LOGOS_PATH|escape:'htmlall':'UTF-8'}logob_{$COMPANY_TYPE|escape:'htmlall':'UTF-8'}.png"
             height="65px;" alt="logo slyr">
      </div>
      <div class="col-md-4 slyr-form-field-block">
        <a href="{$link_all_connectors|escape:'htmlall':'UTF-8'}"
           class="btn btn-success width-150 mar-top-btt-10"><i class="fa fa-plus text-left"
                                                               aria-hidden="true"></i> New connector</a>
      </div>
      <div class="col-md-4 slyr-form-field-block">
        {$purge_button|escape:"quotes"}{$stop_syncronization|escape:"quotes"}
      </div>

    </div>
    <div class="row" id="progressbar"></div>
    <div class="row mar-top-btt-40" id="slyr-import-module-block">
      <div class="mar-top-btt-10 mar-top-btt-40"><h1>
          {$COMPANY_NAME|escape:'htmlall':'UTF-8'} - Update Categories &amp; Products</h1></div>
      <div class="row text-center"><span id="messages">{$messages|escape:"quotes"}</span></div>
      {if isset($SLY_HAS_ERRORS)}
        <div class="sy-alert sy-danger">
          <p>Error! Wrong credentials.</p>
        </div>
      {/if}

      {if isset($SLY_HAS_ERRORS_DELETE_CONNECTOR)}
        <div class="sy-alert sy-danger">
          <p>Error! Couldn't delete connector.</p>
        </div>
      {/if}

      {if (isset($SLY_SUCCESS) && $SLY_SUCCESS == 1)}
        <div class="sy-alert sy-done">
          <p>The synchronization has been completed.</p>
        </div>
      {/if}

      <table class="table table-sm mar-top-btt-10">
        <thead>
        <tr>
          <th class="text-center"><i class="fa fa-plug fa-2x" aria-hidden="true"></i></th>
          <th><h4>Connection Details</h4></th>
          <th><h4>Shops to synchronize</h4></th>
          <th class="text-center"><h4>Autosync every</h4></th>
          <th class="text-center"><h4>Preferred time</h4></th>
          <th class="text-center"><h4>Overwrite stock status</h4></th>
          <th class="text-center"><h4>Remove connector</h4></th>
          {$SLY_DEVELOPMENT|escape:"quotes"}
        </tr>
        </thead>
        <tbody>
        {$SLY_TABLE|escape:"quotes"}
        </tbody>
      </table>
    </div>
  </div>
{literal}
  <script>
    var timerCheck;
    var timeout;

    function validAutosync(data) {
      var connector_id = data.id.replace(data.name + '_', '');
      // var field_name = data.name;
      var field_value = data.value;
      if (field_value == 0) {
        document.getElementById('head_' + connector_id).classList.remove('green_sl');
        document.getElementById('head_' + connector_id).classList.add('grey_sl');
        document.getElementById('head_b_' + connector_id).innerHTML = 'OFF'
      } else {
        document.getElementById('head_' + connector_id).classList.remove('grey_sl');
        document.getElementById('head_' + connector_id).classList.add('green_sl');
        document.getElementById('head_b_' + connector_id).innerHTML = 'ON'
      }
      if (field_value > 23) {
        document.getElementById('auto_sync_hour_' + connector_id).disabled = false
      } else {
        document.getElementById('auto_sync_hour_' + connector_id).disabled = true
      }
    }

    function update_conn_field(data) {
      var connector_id = data.id.replace(data.name + '_', '');
      var token = $('#mymodule_wrapper').attr('data-token');
      var field_name = data.name;
      if (data.type === 'checkbox') {
        var field_value = data.checked;
        if (field_value === true) {
          field_value = 1
        } else {
          field_value = 0
        }
      } else {
        var field_value = data.value
      }
      jQuery.ajax({
        type: 'POST',
        url: $('#ajax_link_sl').val(),
        dataType: 'json',
        data: {
          'connector_id': connector_id,
          'field_name': field_name,
          'field_value': field_value,
          'token': token
        },
        success: function (data_return) {
          $('.server_time').html(data_return['server_time']);
          showMessage(data_return['message_type'], data_return['message'])
        },
        error: function (data_return) {
          showMessage(data_return['message_type'], data_return['message'])
        }
      })
    }

    function update_command(data) {
      var connector_id = data.id.replace(data.name + '_', '');
      var token = $('#mymodule_wrapper').attr('data-token');
      var command = data.name;

      if (command == 'delete_now') {
        if (confirm('You really want to delete the connector?') == true) {
        } else {
          return false
        }
      }
      showMessage('success', 'Proccessing...');
      jQuery.ajax({
        type: 'POST',
        url: $('#ajax_link_sl').val(),
        dataType: 'json',
        data: {'connector_id': connector_id, 'command': command, 'token': token},
        success: function (data_return) {
          check_status();
          if (command == 'delete_now') {
            $('#connector_register_' + connector_id).html()
          } else {
            $('.server_time').html(data_return['server_time']);
            showMessage(data_return['message_type'], data_return['message'])

          }
        },
        error: function (data_return) {
          showMessage(data_return['message_type'], data_return['message'])
        }
      })
    }

    function check_status() {
      var token = $('#mymodule_wrapper').attr('data-token');
      var command = 'check_status';

      jQuery.ajax({
        type: 'POST',
        url: $('#ajax_link_sl').val(),
        dataType: 'json',
        data: {'command': command, 'token': token},
        success: function (data_return) {
          $('.server_time').html(data_return['server_time']);
          if (data_return['status'] == 'processing') {
            var start = document.getElementById('allelements').value;
            if (start == 0) {
              document.getElementById('allelements').value = parseInt(data_return['actual_stat']);
              showProgressBarSL(0, start, data_return['next_cron_expected']);
              clearInterval(timerCheck);
              timerCheck = setInterval(function () {
                check_status()
              }, 2000)
            } else {
              if (parseInt(start) < parseInt(data_return['actual_stat'])) {
                start = parseInt(data_return['actual_stat']);
                document.getElementById('allelements').value = data_return['actual_stat']
              }
              $('#messages').html('');
              clearTimeout(timeout);
              var actual = start - parseInt(data_return['actual_stat']);
              showProgressBarSL(actual, start, data_return['next_cron_expected']);
              //   console.log('status start->'+ start +'  now->'+ data_return['actual_stat'] +' actual->' +actual);
              if (actual == start) {
                clearInterval(timerCheck);
                timerCheck = setInterval(function () {
                  check_status()
                }, 10000)
              }
            }
          } else {
            clearInterval(timerCheck);
            timerCheck = setInterval(function () {
              check_status()
            }, 10000);
            document.getElementById('progressbar').innerHTML = '';
            document.getElementById('allelements').value = 0
          }
        },
        error: function () {
          document.getElementById('allelements').value = 0
        }
      })
    }

    function showProgressBarSL(status, total, time = null) {

      var onePr = total / 100;
      var statPr;
      if (status == 0) {
        if (time == null) {
          showMessage('success', 'Please wait a few minutes, until cron job of Prestashop executes syncronization...')
        } else {
          showMessage('success', 'Please wait a ' + time + ' , until cron job of Prestashop executes syncronization...')
        }
        statPr = 0
      } else {
        statPr = Math.round(status / onePr)
      }
      var div;
      var text_color;
      if (statPr > 52) {
        text_color = 'text-white'
      } else {
        text_color = 'text-body'
      }
      div = '<div class="row"><div class="progress-bar " role="progressbar" style="width:' + statPr + '%" aria-valuenow="' + statPr + '" aria-valuemin="0" aria-valuemax="100"></div></div><span class="text-center ' + text_color + '">' + statPr + '%  (' + status + '/' + total + ')</span>';
      document.getElementById('progressbar').innerHTML = div

    }

    function showMessage(type = 'success', message) {
      var html = '<ul class="messages"><li class="' + type + '-msg"><ul><li>' + message + '</li></ul></li></ul>';
      $('#messages').html(html);
      clear_messege_status()
    }

    function clear_messege_status() {
      clearTimeout(timeout);
      timeout = setTimeout(function () {
        $('#messages').html('');
        clearTimeout(timeout)
      }, 12000)
    }

    document.addEventListener('DOMContentLoaded', function () {
      check_status()
    }, false)

  </script>
{/literal}
{/strip}