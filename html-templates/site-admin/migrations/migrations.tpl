{extends designs/site.tpl}

{block title}Migrations &mdash; {$dwoo.parent}{/block}

{block content}
    <table>
        <tr>
            <th scope="col">Migration</th>
            <th scope="col">Status</th>
            <th scope="col">Timestamp</th>
            <th scope="col"></th>
        </tr>
        
        {foreach item=migration from=$data}
            <tr>
                <td>{$migration.key|escape}<br><small>SHA1: {$migration.sha1}</td>
                <td>{$migration.status}</td>
                <td>{$migration.executed}</td>
                <td>
                    {if $migration.status == 'new'}
                        <form action="/site-admin/migrations/{$migration.key|escape}" method="POST">
                            <input type="submit" value="Execute">
                        </form>
                    {/if}
                </td>
            </tr>
        {/foreach}
    </table>
{/block}