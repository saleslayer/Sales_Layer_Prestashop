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

<head>{literal}
  <!--<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">-->
  <!--<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.2.0/css/all.css" integrity="sha384-hWVjflwFxL6sNzntih27bfxkr27PmbbK/iSvJ+a4+0owXq79v+lsFkW54bOGbiDQ" crossorigin="anonymous">-->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <style>
    /*! CSS Used from: https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css */
    img{border:0}
    button,input{margin:0;font:inherit;color:inherit}
    button{overflow:visible;text-transform:none;-webkit-appearance:button;cursor:pointer}
    button::-moz-focus-inner,input::-moz-focus-inner{padding:0;border:0}
    input{line-height:normal}
    input[type=checkbox]{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;padding:0}
    table{border-spacing:0;border-collapse:collapse}
    td,th{padding:0}
    @media print {
      *,:after,:before{color:#000!important;text-shadow:none!important;background:0 0!important;-webkit-box-shadow:none!important;box-shadow:none!important}
      thead{display:table-header-group}
      img,tr{page-break-inside:avoid}
      img{max-width:100%!important}
      .table{border-collapse:collapse!important}
      .table td,.table th{background-color:#fff!important}
    }
    *{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}
    :after,:before{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}
    button,input{font-family:inherit;font-size:inherit;line-height:inherit}
    img{vertical-align:middle}
    .text-center{text-align:center}
    .text-warning{color:#8a6d3b}
    .text-success{color:#5cb85c}
    .text-danger{color:#a94442}
    .container-fluid{padding-right:15px;padding-left:15px;margin-right:auto;margin-left:auto}
    .row{margin-right:-15px;margin-left:-15px}
    .col-lg-2,.col-lg-4,.col-lg-5,.col-lg-8,.col-md-4,.col-md-8,.col-sm-12,.col-xs-12{position:relative;min-height:1px;padding-right:15px;padding-left:15px}
    .col-xs-12{float:left;width:100%}
    @media (min-width: 768px) {
      .col-sm-12{float:left;width:100%}
    }
    @media (min-width: 992px) {
      .col-md-4,.col-md-8{float:left}
      .col-md-8{width:66.66666667%}
      .col-md-4{width:33.33333333%}
    }
    @media (min-width: 1200px) {
      .col-lg-2,.col-lg-4,.col-lg-5,.col-lg-8{float:left}
      .col-lg-8{width:66.66666667%}
      .col-lg-5{width:41.66666667%}
      .col-lg-4{width:33.33333333%}
      .col-lg-2{width:16.66666667%}
    }
    table{background-color:transparent}
    th{text-align:left}
    .table{width:100%;max-width:100%;margin-bottom:20px}
    .table > tbody > tr > td,.table > thead > tr > th{padding:8px;line-height:1.42857143;vertical-align:top;border-top:1px solid #ddd}
    .table > thead > tr > th{vertical-align:bottom;border-bottom:2px solid #ddd}
    .table > thead:first-child > tr:first-child > th{border-top:0}
    .table-hover > tbody > tr:hover{background-color:#f5f5f5}
    .table-responsive{min-height:.01%;overflow-x:auto}
    @media screen and (max-width: 767px) {
      .table-responsive{width:100%;margin-bottom:15px;overflow-y:hidden;-ms-overflow-style:-ms-autohiding-scrollbar;border:1px solid #ddd}
      .table-responsive > .table{margin-bottom:0}
      .table-responsive > .table > tbody > tr > td,.table-responsive > .table > thead > tr > th{white-space:nowrap}
    }
    input[type=checkbox]{margin:4px 0 0;margin-top:1px \9;line-height:normal}
    input[type=checkbox]:focus{outline:5px auto -webkit-focus-ring-color;outline-offset:-2px}
    .btn{display:inline-block;padding:6px 12px;margin-bottom:0;font-size:14px;font-weight:400;line-height:1.42857143;text-align:center;white-space:nowrap;vertical-align:middle;-ms-touch-action:manipulation;touch-action:manipulation;cursor:pointer;-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none;background-image:none;border:1px solid transparent;border-radius:4px}
    .btn:active:focus,.btn:focus{outline:5px auto -webkit-focus-ring-color;outline-offset:-2px}
    .btn:focus,.btn:hover{color:#333;text-decoration:none}
    .btn:active{background-image:none;outline:0;-webkit-box-shadow:inset 0 3px 5px rgba(0,0,0,.125);box-shadow:inset 0 3px 5px rgba(0,0,0,.125)}
    .btn-success{color:#fff;background-color:#5cb85c;border-color:#4cae4c}
    .btn-success:focus{color:#fff;background-color:#449d44;border-color:#255625}
    .btn-success:hover{color:#fff;background-color:#449d44;border-color:#398439}
    .btn-success:active{color:#fff;background-color:#449d44;border-color:#398439}
    .btn-success:active:focus,.btn-success:active:hover{color:#fff;background-color:#398439;border-color:#255625}
    .btn-success:active{background-image:none}
    .btn-danger{color:#fff;background-color:#d9534f;border-color:#d43f3a}
    .btn-danger:focus{color:#fff;background-color:#c9302c;border-color:#761c19}
    .btn-danger:hover{color:#fff;background-color:#c9302c;border-color:#ac2925}
    .btn-danger:active{color:#fff;background-color:#c9302c;border-color:#ac2925}
    .btn-danger:active:focus,.btn-danger:active:hover{color:#fff;background-color:#ac2925;border-color:#761c19}
    .btn-danger:active{background-image:none}
    .btn-xs{padding:1px 5px;font-size:12px;line-height:1.5;border-radius:3px}
    .btn-group{position:relative;display:inline-block;vertical-align:middle}
    .btn-group > .btn{position:relative;float:left}
    .btn-group > .btn:active,.btn-group > .btn:focus,.btn-group > .btn:hover{z-index:2}
    .btn-group .btn + .btn{margin-left:-1px}
    .btn-group > .btn:first-child{margin-left:0}
    .btn-group > .btn:first-child:not(:last-child):not(.dropdown-toggle){border-top-right-radius:0;border-bottom-right-radius:0}
    .btn-group > .btn:last-child:not(:first-child){border-top-left-radius:0;border-bottom-left-radius:0}
    .forinfo > .alert{margin-bottom:20px;border:1px solid transparent;border-radius:0px !important;min-height:64px;min-width:250px;clear:right !important;border-left:0 !important;}
    .forinfo >.alert-info{color:#31708f;background-color:#d9edf7;border-color:#bce8f1}
    .forinfo >.alert-warning{color:#8a6d3b;background-color:#fcf8e3;border-color:#faebcc}
    .forinfo >.alert-danger{color:#a94442;background-color:#f2dede;border-color:#ebccd1}
    .container-fluid:after,.container-fluid:before,.row:after,.row:before{display:table;content:" "}
    .container-fluid:after,.row:after{clear:both}
    .pull-left{float:left!important}
    .far,.fas{-moz-osx-font-smoothing:grayscale;-webkit-font-smoothing:antialiased;display:inline-block;font-style:normal;font-variant:normal;text-rendering:auto;line-height:1}
    .fa-check-square:before{content:"\f14a"}
    .fa-exclamation-circle:before{content:"\f06a"}
    .fa-exclamation-triangle:before{content:"\f071"}
    .fa-info:before{content:"\f129"}
    .fa-sync-alt:before{content:"\f2f1"}
    .fa-times:before{content:"\f00d"}
    .fa-times-circle:before{content:"\f057"}
    .fa-trash-alt:before{content:"\f2ed"}
    .fa-info-circle:before{content:"\f05a"}
    .fa-download:before{content:"\f019"}
    .far{font-weight:400}
    .far,.fas{font-family:"Font Awesome 5 Free"}
    .fas{font-weight:900}
    .container-fluid{max-width:100%}
    .mar-top-btt-80{margin-bottom:80px;margin-top:80px}
    .max-w-100{max-width:100%}
    .mar-10{margin:10px}
    .pad-10{padding:10px}
    .pad-40{padding:40px}
    .min-hei-400{min-height:400px}
    .min-hei-40{min-height:40px}
    .min-wid-100{min-width:100px}
    table{font-size:14px;width:100%!important;display:inline-block;word-wrap:break-word}
    table td{word-wrap:break-word}
    .table-responsive{display:table}
    .btn{border-radius:0!important;font-size:14px;font-weight:400}
    .btn-success{background:#4cb58e}
    .btn-success:hover{background:#53c79c}
    .out-box{max-height:900px;overflow-x:auto;overflow-y:auto;padding:15px}
    @media only screen and (max-width: 800px) {
      .pad-40{padding:0}
      .mar-top-btt-80{margin-bottom:40px;margin-top:40px}
    }
    .far,.fas{-moz-osx-font-smoothing:grayscale;-webkit-font-smoothing:antialiased;display:inline-block;font-style:normal;font-variant:normal;text-rendering:auto;line-height:1}
    .fa-check-square:before{content:"\f14a"}
    .fa-exclamation-circle:before{content:"\f06a"}
    .fa{-moz-osx-font-smoothing:grayscale;-webkit-font-smoothing:antialiased;display:inline-block;font-style:normal;font-variant:normal;text-rendering:auto;line-height:1;font-family:"Font Awesome 5 Free";font-weight:900}
    .fa-exclamation-triangle:before{content:"\f071"}
    .fa-info:before{content:"\f129"}
    .fa-sync-alt:before{content:"\f2f1"}
    .fa-times:before{content:"\f00d"}
    .fa-times-circle:before{content:"\f057"}
    .fa-trash-alt:before{content:"\f2ed"}
    .far{font-weight:400}
    .far,.fas{font-family:"Font Awesome 5 Free"}
    .fas{font-weight:900}
    .container-fluid{max-width:100%}
    .mar-top-btt-80{margin-bottom:80px;margin-top:80px}
    .max-w-100{max-width:100%}
    .max-h-50p{max-height:50px}
    .mar-5{margin:5px}
    .mar-10{margin:10px}
    .pad-10{padding:10px}
    .pad-40{padding:40px}
    .min-hei-400{min-height:400px}
    table{font-size:14px;width:100%!important;display:inline-block;word-wrap:break-word}
    table td{word-wrap:break-word}
    .table-active{background-color:rgba(0,0,0,.075)}
    .table-responsive{display:table}
    .forinfo span{font-size:14px}
    .btn{border-radius:0!important;font-size:14px;font-weight:400}
    .btn-success{background:#4cb58e}
    .btn-success:hover{background:#53c79c}
    .out-box{max-height:600px;overflow-x:auto;overflow-y:auto;padding:15px}
    .loader-base{position:relative}
    .loader{position:absolute;display:none;width:100%;height:100%;background-color:grey;opacity:.3;z-index:4}
    .loader-icon {
      position: absolute;
      display: none;
      background: url('{/literal}{$SLY_LOGOS_PATH|escape:'htmlall':'UTF-8'}{literal}loading-icon.svg') no-repeat;
      z-index: 5;
      width: 100px;
      height: 100px;
      left: 50%;
      top: 500px;
    }
    .badge{
      border-radius: 2px !important;
    }
    .badge-secondary{
      background-color: #6c757d !important;
    }
    .badge-success{
      background-color: #1a712c !important;
    }
    .table-hide-bor, .table-hide-bor tr ,.table-hide-bor td{
      border:0 !important;
    }
    .table-hide-bor > tbody > tr > td,.table-hide-bor > tbody > tr > td:hover {
      background-color: transparent !important;
    }
    #showlog span{margin:2px 0;font-size:12px;word-wrap:break-word}
    @media only screen and (max-width: 800px) {
      .pad-40{padding:0}
      .mar-top-btt-80{margin-bottom:40px;margin-top:40px}
    }
    @font-face{font-family:"Font Awesome 5 Free";font-style:normal;font-weight:400;src:url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-regular-400.eot);src:url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-regular-400.eot#iefix) format("embedded-opentype"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-regular-400.woff2) format("woff2"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-regular-400.woff) format("woff"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-regular-400.ttf) format("truetype"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-regular-400.svg#fontawesome) format("svg")}
    @font-face{font-family:"Font Awesome 5 Free";font-style:normal;font-weight:900;src:url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-solid-900.eot);src:url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-solid-900.eot#iefix) format("embedded-opentype"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-solid-900.woff2) format("woff2"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-solid-900.woff) format("woff"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-solid-900.ttf) format("truetype"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-solid-900.svg#fontawesome) format("svg")}
    @font-face{font-family:"Font Awesome 5 Free";font-style:normal;font-weight:900;src:url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-solid-900.eot);src:url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-solid-900.eot#iefix) format("embedded-opentype"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-solid-900.woff2) format("woff2"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-solid-900.woff) format("woff"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-solid-900.ttf) format("truetype"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-solid-900.svg#fontawesome) format("svg")}
    @font-face{font-family:"Font Awesome 5 Free";font-style:normal;font-weight:400;src:url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-regular-400.eot);src:url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-regular-400.eot#iefix) format("embedded-opentype"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-regular-400.woff2) format("woff2"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-regular-400.woff) format("woff"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-regular-400.ttf) format("truetype"),url(https://use.fontawesome.com/releases/v5.2.0/webfonts/fa-regular-400.svg#fontawesome) format("svg")}
  </style>{/literal}{strip}
</head>
<body>
<div class="container-fluid mar-top-btt-80 " id="mymodule_wrapper" data-token="{$token|escape:'htmlall':'UTF-8'}">
  <form action="" method="post" autocomplete="off" role="form" class="" id="form_sl_edit">
  <div class="loader" id="ajaxsearchloader"></div>
  <div class="loader-base"><span class="loader-icon"></span></div>
  <div class="row">
    <div class="col-lg-2 col-md-4 col-sm-12 pad-10">
      <div class="row">
        <img src="{$SLY_LOGOS_PATH|escape:'htmlall':'UTF-8'}logob_{$COMPANY_TYPE|escape:'htmlall':'UTF-8'}.png"
             height="65px;" alt="logo sales layer" class="max-h-50p">
      </div>
      <div class="row form-group-sm mar-10">
        <div class="col-sm-4">
          <table class="table table-responsive table-hide-bor">
            <tr>
              <td>
               <label>Debugmode</label>
              </td>
              <td class="min-hei-40">
                <div>
                <select class="min-wid-100" name="debugmode" title="Generate debug log" onchange="ajaxexecuter(this);">
                  {$SLY_DEBUGMODE_SELECT|escape:"quotes":"UTF-8"}
                </select>
                </div>
              </td>
              <td>
                <label>Synchronization priority</label>
              </td>
              <td class="min-hei-40">
                <div>
                  <select class="min-wid-100" name="performance" title="Set the cpu saturation level of your server. If this limit is doubled, synchronization will begin to be postponed for a few seconds to reduce the load of your cpu." onchange="ajaxexecuter(this);">
                  {$SLY_PERFORMANCE_SELECT|escape:"quotes":"UTF-8"}
                  </select>
                </div>
              </td>
            </tr>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-5 col-md-4 col-xs-12 pad-10" id="slh1selector">
    </div>
    <div class="col-lg-5 col-md-4 col-xs-12 pad-10 forinfo">
      <span class="pull-left" id="sllisted"></span>
      <span class="pull-left" id="slwarnings"></span>
      <span class="pull-left" id="slerrors"></span>
      <span id="messages"></span>
    </div>
  </div>
  <div class="row mar-top-btt-80">
    <div class="col-lg-4 col-md-4 col-sm-12">
      <div class="w-100 btn-group btn-group-toggle">
        <input type="hidden" name="ajaxloading" id="ajaxonline" value="0"/>
        <input type="hidden" name="statline" id="statline" value="0"/>
        <input type="hidden" name="download" id="downloadfile" value="" />
        <button type="button" class="btn btn-success btn-xs" title="Reload Logs" onclick="ajaxexecuter(this);"
                name="showlogfilesbutt"><i class="fas fa-sync-alt"></i> Reload logs
        </button>
        <button type="button" class="btn btn-danger btn-xs" title="Delete logs" onclick="ajaxexecuter(this);"
                name="deletelogfile"><i class="fas fa-trash-alt"></i> Remove logs
        </button>
      </div>
      <div class="w-100 table-responsive">
        <table class="table table-hover w-100 max-w-100" id="tablelogs">
          <thead>
          <tr>
            <th scope="col"><i class="far fa-check-square" onclick="checkcheckboxes();"></i></th>
            <th scope="col">Name of log file</th>
            <th scope="col">Lines</th>
            <th scope="col" class="text-center"><i class="fas fa-exclamation-circle text-warning"></i></th>
            <th scope="col" class="text-center"><i class="fas  fa-times-circle text-danger"></i></th>
            <th scope="col" class="text-center"><i class="fa fa-download" aria-hidden="true"></i></th>
          </tr>
          </thead>
          <tbody id="listoflogs">
          {$log_files|escape:"quotes":"UTF-8"}
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-lg-8 col-md-8 col-sm-12 pad-40 min-hei-400">
      <div id="showlog" class="w-100 min-hei-400 out-box"></div>
    </div>
  </div>
  </form>
</div>

{/strip}{literal}
<script>
  var errorids_arr = [];
  var latest_error = 0;
  var warnings_arr = [];
  var latest_warning = 0;

  function showerrors() {
    var errors = document.getElementById('slerrors');
    if (errorids_arr.length > 0) {
      errors.innerHTML = errorids_arr.length + ' Errors';
      errors.classList.add('alert');
      errors.classList.add('alert-danger');
      errors.setAttribute('onclick', 'jumpToNextError()');
      var faicon = document.createElement('i');
      faicon.setAttribute('class', 'fas fa-times mar-10');
      errors.insertBefore(faicon, errors.childNodes[0])
      // console.log('Errors in document->'+errorids_arr.length);
    } else {
      errors.innerHTML = '';
      errors.classList.remove('alert');
      errors.classList.remove('alert-danger')
    }
  }

  function showwarnings() {
    var warning = document.getElementById('slwarnings');
    if (warnings_arr.length > 0) {
      warning.innerHTML = warnings_arr.length + ' Warnings';
      warning.classList.add('alert');
      warning.classList.add('alert-warning');
      warning.setAttribute('onclick', 'jumpToNextWarning()');
      var faicon = document.createElement('i');
      faicon.setAttribute('class', 'fas fa-exclamation-triangle mar-10');
      warning.insertBefore(faicon, warning.childNodes[0])
      //  console.log('warnings in document->'+warnings_arr.length);
    } else {
      warning.innerHTML = '';
      warning.classList.remove('alert');
      warning.classList.remove('alert-warning')
    }
  }

  function NumberFormat(num) {
    return num.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1 ')
  }

  function jumpToNextError() {

    var key;
    for (var i = 0; i < errorids_arr.length; i++) {
      if (errorids_arr[i] == latest_error) {
        i++;
        latest_error = errorids_arr[i];
        if (errorids_arr[i] == errorids_arr[errorids_arr.length]) {
          key = 0;
          latest_error = errorids_arr[0]
        } else {
          key = i
        }
        window.location.href = '#error' + errorids_arr[key];
        return false
      }
    }
    window.location.href = '#error' + errorids_arr[0];
    latest_error = errorids_arr[0];
    return false

  }

  function jumpToNextWarning() {
    var key;
    for (var i = 0; i < warnings_arr.length; i++) {
      if (warnings_arr[i] == latest_warning) {
        i++;
        latest_warning = warnings_arr[i];
        if (warnings_arr[i] == warnings_arr[warnings_arr.length]) {
          key = 0;
          latest_warning = warnings_arr[0]
        } else {
          key = i
        }
        window.location.href = '#warning' + warnings_arr[key];
        return false
      }
    }
    window.location.href = '#warning' + warnings_arr[0];
    latest_warning = warnings_arr[0];
    return false
  }

  function objectLength(obj) {
    var result = 0;
    for (var prop in obj) {
      if (obj.hasOwnProperty(prop)) {
        result++
      }
    }
    return result
  }

  function sync_custom_command(command, value) {
    try{
      if (document.getElementById('ajaxonline').value === '0') {
        var sub = 0;
        if (value != 'hide') {
          $('#ajaxsearchloader,.loader-icon').show()
        }
        document.getElementById('ajaxonline').value = '1';
        if (command != 'showlogfilecontent' && command != 'debugmode' && command != 'performance') {
          value = document.getElementById('statline').value
        }
        if(command == 'showlogfilesbutt'){
          sub = 1;
          command = 'showlogfiles';
        }
        jQuery.ajax({
          method: 'POST',
          url: "{/literal}{$diag_link|escape:'htmlall':'UTF-8'}{literal}",
          data: {'logcommand': command, 'token': $('#mymodule_wrapper').attr('data-token'), 'value': value},
          dataType: 'json'
        }).done(function (data_return) {
          var ajax = document.getElementById('ajaxtest');
          if(ajax){
            ajax.classList = 'text-success';
            ajax.innerHTML = 'Ajax works correctly';
          }

          if (data_return['message_type'] === 'success') {
            if (data_return['function'] === 'showlogfiles') {
              if (data_return['content']['file'].length >= 1) {
                document.getElementById('listoflogs').innerHTML = '';
                document.getElementById('showlog').innerHTML = '';
                pagenull();
                //null info
                var lined = document.getElementById('sllisted');
                lined.innerHTML = '';
                lined.classList.remove('alert');
                lined.classList.remove('alert-info');
                //null warnings
                var forwarning = document.getElementById('slwarnings');
                forwarning.innerHTML = '';
                forwarning.classList.remove('alert');
                forwarning.classList.remove('alert-warning');
                //null errors
                var forerrors = document.getElementById('slerrors');
                forerrors.innerHTML = '';
                forerrors.classList.remove('alert');
                forerrors.classList.remove('alert-danger');
                //var i;
                var first;
                if (data_return['content']['file'].length >= 1) {
                  for (var i = 0; i < data_return['content']['file'].length; i++) {
                    var tr = document.createElement('tr');
                    if (i === 0) {
                      first = data_return['content']['file'][i];
                      tr.setAttribute('class', 'filesnamestr table-active')
                    } else {
                      tr.setAttribute('class', 'filesnamestr')
                    }
                    var tdc = document.createElement('td');
                    var chk = document.createElement('input');
                    chk.setAttribute('type', 'checkbox');
                    chk.setAttribute('name', 'file[]');
                    chk.setAttribute('value', data_return['content']['file'][i]);
                    tdc.appendChild(chk);

                    var tdp = document.createElement('td');
                    tdp.setAttribute('class', 'filesnames');
                    tdp.onclick = function () {
                      updateInfo(this)
                    };
                    tdp.setAttribute('data', data_return['content']['file'][i]);

                    var node = document.createTextNode(data_return['content']['file'][i]);

                    var tdlines = document.createElement('td');
                    var nodelines = document.createTextNode(NumberFormat(data_return['content']['lines'][i]));
                    tdlines.appendChild(nodelines);

                    var tdwarnings = document.createElement('td');
                    if (data_return['content']['warnings'][i] >= 1) {
                      var nodewarnings = document.createTextNode(data_return['content']['warnings'][i]);
                      tdwarnings.setAttribute('class', 'text-center text-warning');
                      tdwarnings.appendChild(nodewarnings)
                    }

                    var tderror = document.createElement('td');
                    if (data_return['content']['errors'][i] >= 1) {
                      var nodeerror = document.createTextNode(data_return['content']['errors'][i]);
                      tderror.setAttribute('class', 'text-center text-danger');
                      tderror.appendChild(nodeerror)
                    }
                    var downloadbtt = document.createElement('td');

                      var nodedownload = document.createElement('span');
                          nodedownload.setAttribute('onclick', 'downloadlogfile("'+data_return['content']['file'][i].trim()+'")');
                          nodedownload.setAttribute('class', 'btn btn-xs');
                        var nodedownloadicon = document.createElement('i');
                            nodedownloadicon.setAttribute('aria-hidden', 'true');
                            nodedownloadicon.setAttribute('class', 'fa fa-download');
                            nodedownload.appendChild(nodedownloadicon);

                    downloadbtt.setAttribute('class', 'text-center');
                    downloadbtt.appendChild(nodedownload);

                    tdp.appendChild(node);
                    tr.appendChild(tdc);
                    tr.appendChild(tdp);
                    tr.appendChild(tdlines);
                    tr.appendChild(tdwarnings);
                    tr.appendChild(tderror);
                    tr.appendChild(downloadbtt);

                    var parent = document.getElementById('listoflogs');
                    parent.appendChild(tr);

                    function updateInfo(dataevent) {
                      document.getElementById('slh1selector').innerHTML = '';
                      var commandfor = dataevent.getAttribute('data');
                      pagenull();
                      sync_custom_command(commandfor, '');
                      var h1 = document.createElement('h3');
                      var div = document.getElementById('slh1selector');
                      var node = document.createTextNode(commandfor);
                      h1.appendChild(node);
                      div.appendChild(h1)
                    }
                  }
                  document.getElementById('ajaxonline').value = '0'
                  // sync_custom_command(first,'');
                }
              }
            } else if (data_return['function'] === 'showlogfilecontent') {
              var divcontenedor = document.getElementById('showlog');
              if (document.getElementById('statline').value == 0) {
                document.getElementById('showlog').innerHTML = '';
                errorids_arr = [];
                warnings_arr = [];
                latest_error = 0;
                latest_warning = 0;
                document.getElementById('ajaxonline').value = '1'
              }
              var table = document.getElementById('listoflogs');
              var trs = table.getElementsByClassName('filesnamestr');
              for (var i = 0; i < trs.length; i++) {
                trs[i].addEventListener('click', function () {
                  var current = document.getElementsByClassName('table-active');
                  if (current.length > 0) {
                    current[0].className = current[0].className.replace('table-active', '')
                  }
                  this.className += ' table-active'
                })
              }
              var i = value;
              var count_obj = parseInt(i) + parseInt(objectLength(data_return['content']));

              for (; i < count_obj; i++) {

                var jsSpan = document.createElement('span');
                if (data_return['content']['l-' + i]['spacing'].length > 0) {
                  jsSpan.innerHTML = data_return['content']['l-' + i]['spacing']
                }
                if (data_return['content']['l-' + i]['stat'] == 'error') {
                  jsSpan.setAttribute('id', 'error' + i);
                  jsSpan.setAttribute('class', 'alert-danger');
                  var text = document.createTextNode(data_return['content']['l-' + i]['content']);
                  var icon = document.createElement('i');
                  icon.setAttribute('class', 'fas fa-times text-danger mar-10');
                  jsSpan.appendChild(icon);
                  jsSpan.appendChild(text);
                  errorids_arr.push(i)
                } else if (data_return['content']['l-' + i]['stat'] == 'warning') {
                  jsSpan.setAttribute('id', 'warning' + i);
                  jsSpan.setAttribute('class', 'alert-warning');
                  var text = document.createTextNode(data_return['content']['l-' + i]['content']);
                  var icon = document.createElement('i');
                  icon.setAttribute('class', 'fas fa-times text-danger mar-10');
                  jsSpan.appendChild(icon);
                  jsSpan.appendChild(text);
                  warnings_arr.push(i)
                } else if (data_return['content']['l-' + i]['stat'] == 'info') {
                  jsSpan.setAttribute('id', 'info' + i);
                  jsSpan.setAttribute('class', 'alert-info');
                  var text = document.createTextNode(data_return['content']['l-' + i]['content']);
                  var icon = document.createElement('i');
                  icon.setAttribute('class', 'fas fa-info-circle text-info mar-10');
                  jsSpan.appendChild(icon);
                  jsSpan.appendChild(text)
                } else {
                  jsSpan.setAttribute('id', 'element' + i);
                  var text = document.createTextNode(data_return['content']['l-' + i]['content']);
                  jsSpan.appendChild(text)
                }

                var br = document.createElement('br');
                jsSpan.appendChild(br);
                divcontenedor.appendChild(jsSpan)
              }
              data_return['content'] = [];
              delete (data_return['content']);
              if (data_return['statline'] > 0) {
                document.getElementById('statline').value = data_return['statline']
              }
              var listed = document.getElementById('sllisted');
              var downloaded_lines = data_return['statline'];
              //avisos de error
              if (downloaded_lines >= 1) {
                downloaded_lines--;
                //listed.innerHTML = data_return['lines'] + ' Lines';
                listed.innerHTML = NumberFormat(downloaded_lines) + ' Lines';
                listed.classList.add('alert');
                listed.classList.add('alert-info');
                var faicon = document.createElement('i');
                faicon.setAttribute('class', 'fas fa-info mar-10');
                listed.insertBefore(faicon, listed.childNodes[0])
              } else {
                listed.innerHTML = '';
                listed.classList.remove('alert');
                listed.classList.remove('alert-info')
              }
              showerrors();
              showwarnings();

              if (data_return['lines'] > 0) {
                if (data_return['statline'] < data_return['lines']) {
                  document.getElementById('ajaxonline').value = '0';
                  data_return = [];
                  delete (data_return);
                  sync_custom_command(command, 'hide')
                }
              }
            }
          } else {
           // showMessage(data_return['message_type'], data_return['content']);
            clear_messege_status()
          }
          document.getElementById('ajaxonline').value = '0'
        }).fail(function () {
          var ajax = document.getElementById('ajaxtest');
          if(ajax){
            ajax.classList = 'text-danger';
            ajax.innerHTML = 'It does not work';
          }
          console.log('Ajax connection error, trying send document as post');
          if(command == 'debugmode' || command == 'performance'){
            document.getElementById('downloadfile').value = "";
            document.getElementById('form_sl_edit').submit();
          }
          if(command == 'showlogfiles' && sub == 1){
            document.getElementById('downloadfile').value = "";
            document.getElementById('form_sl_edit').submit();
          }
          document.getElementById('ajaxonline').value = '0';
        }).always(function () {
          $('#ajaxsearchloader,.loader-icon').hide()
        })
      } else {
        return false
      }
    }catch(error){
      console.log(error);
    }
  }

  function pagenull() {
    document.getElementById('statline').value = 0
  }

  function showMessage(type = 'success', message) {
    document.getElementById('messages').innerHTML = '<ul class="messages"><li class="' + type + '-msg"><ul><li>' + message + '</li></ul></li></ul>';
    clear_messege_status()
  }

  function clear_messege_status() {
    var timeout = setTimeout(function () {
      document.getElementById('messages').innerHTML = '';
      clearTimeout(timeout)
    }, 7000)
  }

  function checkcheckboxes() {
    var aa = document.querySelectorAll('input[type=checkbox]');
    var first = true;
    for (var i = 0; i < aa.length; i++) {
      first = aa[i].checked;
      if (first === false) {
        aa[i].checked = true
      } else {
        aa[i].checked = false
      }
    }
  }

  function ajaxexecuter(data) {
    var command = data.name;
    if (command === 'deletelogfile') {
      var array = [];
      var checkboxes = document.querySelectorAll('input[type=checkbox]:checked');
      for (var i = 0; i < checkboxes.length; i++) {
        array.push(checkboxes[i].value)
      }
      if (array.length >= 1) {
        files_for_delete(array)
      }
    } else {
      var value = 0;
      if (data.value != 'undefined') {
        value = data.value
      }
      try{
        sync_custom_command(command, value);
      }catch(e){
        console.log(e);
      }

    }
  }

  function downloadlogfile(filename)
  {
      document.getElementById('downloadfile').value = filename;
      document.getElementById('form_sl_edit').submit();
  }

  function files_for_delete(command) {
    if (document.getElementById('ajaxonline').value === '0') {
      $('#ajaxsearchloader,.loader-icon').show();
      document.getElementById('ajaxonline').value = '1';
      jQuery.ajax({
        method: 'POST',
        url: "{/literal}{$delete_link|escape:'htmlall':'UTF-8'}{literal}",
        data: {'logfilesfordelete': command, 'token': $('#mymodule_wrapper').attr('data-token')},
        dataType: 'json'
      }).done(function (data_return) {
        document.getElementById('listoflogs').innerHTML = '';
        document.getElementById('showlog').innerHTML = '';
        //null info
        var listed = document.getElementById('sllisted');
        listed.innerHTML = '';
        listed.classList.remove('alert');
        listed.classList.remove('alert-info');
        //null warnings
        var warning = document.getElementById('slwarnings');
        warning.innerHTML = '';
        warning.classList.remove('alert');
        warning.classList.remove('alert-warning');
        //null errors
        var errors = document.getElementById('slerrors');
        errors.innerHTML = '';
        errors.classList.remove('alert');
        errors.classList.remove('alert-danger');
        document.getElementById('slh1selector').innerHTML = '';
        if (data_return['message_type'] === 'success') {
          document.getElementById('ajaxonline').value = '0';
          sync_custom_command('showlogfiles', '')
        }
      }).fail(function () {
        console.log('Ajax connection error delete logs');
        if(command.length > 0){
          document.getElementById('form_sl_edit').submit();
          var formdiv = document.getElementById('form_sl_edit');
          for(var i = 0; i< command.length; i++){
            var input = document.createElement('input');
                input.setAttribute('type','hidden');
                input.setAttribute('name','fordelete[]');
                input.setAttribute('value',command[i]);
                formdiv.appendChild(input);
          }
          document.getElementById('form_sl_edit').submit();
        }
        document.getElementById('ajaxonline').value = '0';
      }).always(function () {
        $('#ajaxsearchloader,.loader-icon').hide()
      })
    } else {
      return false
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    sync_custom_command('showlogfiles', '')
  }, false)
</script>{/literal}
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-12 col-md-12 col-sm-12">{$server_aditionalInfo|escape:"quotes":"UTF-8"}</div>
  </div>
</div>
</body>
