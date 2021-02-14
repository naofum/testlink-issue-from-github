{*
 Testlink Issue from GitHub Plugin
 @filesource  completed.tpl
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
            {else }
                <input type="text" name="id" value="{$gui->id}"/>
            {/if}
        </div>
        <div>
            <input type="button" name="cancel" value="{$gui->labelCancel}" onclick="top.location.href='/index.php';"/>
        </div>
    </form>
</div>
