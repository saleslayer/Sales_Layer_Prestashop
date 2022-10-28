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
    <form action="" method="post" autocomplete="off" role="form" class="" id="form_sl_edit">
    <input type="hidden" id="ajax_link_sl" value="{$ajax_link|escape:'htmlall':'UTF-8'}" />
    <input type="hidden" id="allelements" value="0" />
    <input type="hidden" id="del_conn" name="del_conn" value="" />
    <input type="hidden" id="sync_conn" name="sync_conn" value="" />
    <input type="hidden" id="clear_syncronization" name="clear_syncronization" value="" />
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
        {$purge_button|escape:"quotes":"UTF-8"}{$stop_syncronization|escape:"quotes":"UTF-8"}
      </div>
    </div>
    <div class="row" id="progressbar"></div>
    <div class="row mar-top-btt-40" id="slyr-import-module-block">
      <div class="col-md-11 mar-top-btt-40"><h1>
          {$COMPANY_NAME|escape:'htmlall':'UTF-8'} - Update Categories &amp; Products</h1>
      </div>
      <div class="col-md-1 mar-top-btt-40" id="health-class">
        <div class="row mar-5"><span title="Cpu usage">Cpu</span><span style="float:right" id="cpuv"></span></div>
        <div class="row mar-5"><span title="Memory usage">Mem</span><span style="float:right" id="memv"></span></div>
        <div class="row mar-5"><span title="Swap memory usage">Swp</span><span style="float:right" id="swpv"></span></div>
      </div>
      <div class="row text-center">
        <span id="messages">{$messages|escape:"quotes":"UTF-8"}</span></div>
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

      <table class="table table-sm mar-top-btt-10 table-responsive table-hide-bor">
        <thead>
        <tr>
          <th class="text-center"><i class="fa fa-plug fa-2x" aria-hidden="true"></i></th>
          <th><h4>Connection Details</h4></th>
          <th><h4>Shops to synchronize</h4></th>
          <th class="text-center"><h4>Autosync every</h4></th>
          <th class="text-center"><h4>Preferred time</h4></th>
          <th class="text-center"><h4>Overwrite stock status</h4></th>
          <th class="text-center"><h4>Remove connector</h4></th>
          <th class="text-center"><h4>Store data now</h4></th>
          {$SLY_DEVELOPMENT|escape:"quotes":"UTF-8"}
        </tr>
        </thead>
        <tbody>
        {$SLY_TABLE|escape:"quotes":"UTF-8"}
        </tbody>
      </table>
      <div class="col-md-12 hide" id="submit_btt">
        <button type="submit" name="savechanges" class="btn btn-success mar-10 mar-top-btt-40 right" onclick="isSubmiting();"><i class="fa fa-floppy-o" aria-hidden="true"></i> Save Changes</button>
      </div>
      <div class="btn-group-toggle">
      <a href="https://github.com/saleslayer/Sales_Layer_Prestashop/blob/master/Sales%20Layer%20Prestashop%20manual%20(ES).pdf" title="Documentation ES" class="btn btn-success mar-top-btt-40 mar-5" target="_blank"><i class="fa fa-book fa-2x" aria-hidden="true"></i> ES</a>
      <a href="https://github.com/saleslayer/Sales_Layer_Prestashop/blob/master/Sales%20Layer%20Prestashop%20manual%20(EN).pdf" title="Documentation EN" class="btn btn-success mar-top-btt-40 mar-5" target="_blank"><i class="fa fa-book fa-2x" aria-hidden="true"></i> EN</a>
      </div>
    </div>
    </form>
  </div>
{literal}
  <script>
    var timerCheck;
    var timeout;

    function validAutosync(data) {
      var connector_id = data.id.replace(data.name + '_', '');
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
        document.getElementById('auto_sync_hour[' + connector_id +']_' + connector_id).disabled = false
      } else {
        document.getElementById('auto_sync_hour[' + connector_id +']_' + connector_id).disabled = true
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
          if(data_return !== null){
              $('.server_time').html(data_return['server_time']);
              showMessage(data_return['message_type'], data_return['message'])
          }else{
              postFormEnable();
             /* showMessage('error', 'Error connect to server. Check your browser console for more information. Press F12');*/
              console.error('Ajax connection error. Please check your ajax connections made in the Network panel -> XHR -> ajax -> Preview, Continue send form with post');
          }
        },
        error: function () {
          postFormEnable();
         // showMessage('error', 'Connection error')
        }
      })
    }

    function update_command(connector_id,command) {
      var token = $('#mymodule_wrapper').attr('data-token');
      if (command == 'delete_now') {
        if (confirm('You really want to delete the connector?') == true) {
        } else {
          return false
        }
      }
      showMessage('success', 'Proccessing...');
      if(command == 'store_data_now'){
          var upbtnn = document.getElementsByClassName('update_btt');
          for(var i = 0; i<upbtnn.length; i++){
            document.getElementsByClassName('update_btt')[i].disabled = true;
          }
      }
      if(command == 'purge_all'){
        if (confirm('WARNING! Are you sure you want to remove everything from this prestashop installation? ' +
          '(Products, categories, attributes,..... ) and have prestashop completely like new?')) {
        } else {
          return false;
        }
      }

      jQuery.ajax({
        type: 'POST',
        url: $('#ajax_link_sl').val(),
        dataType: 'json',
        data: {'connector_id': connector_id, 'command': command, 'token': token},
        success: function (data_return) {
          check_status();
          if (command == 'delete_now') {
            document.getElementById('connector_register_' + connector_id).remove();
          } else {
            if(command == 'store_data_now'){
              var upbtnn = document.getElementsByClassName('update_btt');
              for(var i = 0; i<upbtnn.length; i++){
                document.getElementsByClassName('update_btt')[i].disabled = false;
              }
            }
            $('.server_time').html(data_return['server_time']);
            showMessage(data_return['message_type'], data_return['message']);
          }
        },
        error: function () {
          if(command == 'store_data_now'){
            showMessage('success', 'We are sending your request to synchronize, wait a few minutes to complete the process.');
            var upbtnn = document.getElementsByClassName('update_btt');
            for(var i = 0; i<upbtnn.length; i++){
              document.getElementsByClassName('update_btt')[i].disabled = false;
            }
            document.getElementById('sync_conn').value = connector_id;
            document.getElementById('form_sl_edit').submit();

          }
          if(command == 'delete_now') {
              document.getElementById('del_conn').value = connector_id;
              document.getElementById('form_sl_edit').submit();
          }
          if(command == 'clear_syncronization') {
            document.getElementById('clear_syncronization').value = 1;
            document.getElementById('form_sl_edit').submit();
          }

         /* showMessage('error', 'Ajax connection error');*/
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
              showProgressBarSL(0, start, data_return['next_cron_expected'],data_return['work_stat'],data_return['speed']);
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
              showProgressBarSL(actual, start, data_return['next_cron_expected'],data_return['work_stat'],data_return['speed']);
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
            if(data_return['work_stat']!='undefined'){
              if(data_return['work_stat']!=''){
                showAditionalstatus(data_return['work_stat']);
              }
            }
          }
          //health
          if(data_return['health'] != false ) {
                document.getElementById('cpuv').classList = getSetColor(data_return['health']['cpu']);
                document.getElementById('cpuv').innerHTML = data_return['health']['cpu']+' %';
                document.getElementById('memv').classList = getSetColor(data_return['health']['mem']);
                document.getElementById('memv').innerHTML = data_return['health']['mem']+' %';
                document.getElementById('swpv').classList = getSetColor(data_return['health']['swp']);
                document.getElementById('swpv').innerHTML = data_return['health']['swp']+' %';
          }else{
            document.getElementById('health-class').style.display = 'none';
          }

        },
        error: function () {
          document.getElementById('allelements').value = 0
        }
      })
    }

    function getSetColor(val){
        if(val > 70){
          return 'error-msg';
        }
        if(val > 50){
          return 'warning-msg';
        }
        if(val < 50){
          return 'success-msg';
        }
    }

    function showProgressBarSL(status, total, time = null,show_stat = '',speed = '') {
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
      if (statPr > 60) {
        text_color = 'text-white'
      } else {
        text_color = 'text-body'
      }
      var speed_txt = '';
      if(speed){
        speed_txt = ' ' + speed + 'x';
      }

      div = '<div class="row"><div class="progress-bar " role="progressbar" style="width:' + statPr + '%" aria-valuenow="' + statPr + '" aria-valuemin="0" aria-valuemax="100"></div></div><span class="text-center ' + text_color + '">'+ show_stat + statPr + '%  (' + status + '/' + total + ')' + speed_txt + ' </span>';
      document.getElementById('progressbar').innerHTML = div
    }
    function showAditionalstatus(message){
      div = '<div class="row"><div class="progress-bar " role="progressbar" style="width:0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div></div><span class="text-center text-body">'+ message + '</span>';
      document.getElementById('progressbar').innerHTML = div
    }
    function postFormEnable(){
        showMessage('success', 'Before leaving please save your changes.')
        document.getElementById('submit_btt').classList.remove('hide');
          window.onbeforeunload = function(){
            return 'Before leaving please save your changes.';
          };
    }
    function isSubmiting(){
      window.onbeforeunload = function(){}
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
