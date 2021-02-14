{*
 Testlink Issue from GitHub Plugin
 @filesource  disp.tpl
 Purpose: smarty template - plugin configuration
*}

{include 'inc_head.tpl'}
<h1>{$gui->title}</h1>
<div class="workBack">
    {if $gui->message }
        <div class="user_feedback" style="color: blue;">
            {$gui->message}
        </div>
    {/if}
    <h2>{$gui->headerMessage}</h2>

    <form method="POST">
        <div class="labelHolder">
            <label>{$gui->labelId}</label>
        </div>
        <div>
            {if $gui->id }
                {$gui->id}
                <input type="hidden" name="id" value="{$gui->id}"/>
                <input type="hidden" name="confirm" value="1"/>
            {else }
                <input type="text" name="id" value="{$gui->id}"/>
            {/if}
        </div>
        <div class="labelHolder">
            <label>{$gui->labelUrl}</label>
        </div>
        <div>
            {$gui->url}
        </div>
        <div class="labelHolder">
            <label>{$gui->labelStatus}</label>
        </div>
        <div>
            {$gui->statusCode}
        </div>
        <div class="labelHolder">
            <label>{$gui->labelSummary}</label>
        </div>
        <div><pre>
            {$gui->summary}
        </pre></div>

        <div class="labelHolder">
            <label>{$gui->labelParent}</label>
        </div>
        <div>
            <select name="parent" id="testsuite"></select>
        </div>

        <div class="labelHolder">
            <label>{$gui->labelTestset}</label>
        </div>
        <div>
            <input type="checkbox" name="test1" value="1" checked>{$gui->labelUnittest1}
            <input type="checkbox" name="test2" value="1" checked>{$gui->labelUnittest2}
            <input type="checkbox" name="test3" value="1" checked>{$gui->labelUnittest3}
        </div>


        <div>
            <input type="button" name="cancel" value="{$gui->labelCancel}" onclick="top.location.href='/index.php';"/>
            <input type="submit" name="submit" value="{$gui->labelSubmit}"/>
        </div>
    </form>
</div>
<script>
$.ajax({
  url: '/lib/ajax/gettprojectnodes.php?root_node=1&tcprefix=DP-',
  type: 'POST',
  data: {
    nodd: 1
  },
  dataType: 'json'
}).done(function( data, textStatus, jqXHR ) {
  for (var i = 0; i < data.length; i++) {
    $('#testsuite').append("<option value=\"" + data[i].id + "\">" + data[i].testlink_node_name + "</option>");
  }
}).fail(function( jqXHR, textStatus, errorThrown) {
  //
}).always(function( jqXHR, textStatus) {
  //
});
</script>
