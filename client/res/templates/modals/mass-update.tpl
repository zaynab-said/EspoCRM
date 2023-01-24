<div class="panel panel-default no-side-margin">
<div class="panel-body panel-body-form">

{{#unless fieldList}}
    {{translate 'No fields available for Mass Update'}}
{{else}}

<div class="button-container">
    <button class="btn btn-default pull-right hidden" data-action="reset">{{translate 'Reset'}}</button>
    <div class="btn-group">
        <button
            class="btn btn-default dropdown-toggle select-field"
            data-toggle="dropdown"
            tabindex="-1"
        >{{translate 'Add Field'}} <span class="caret"></span></button>
        <ul class="dropdown-menu pull-left filter-list">
        {{#each fieldList}}
            <li
                data-name="{{./this}}"
            ><a
                role="button"
                tabindex="0"
                data-name="{{./this}}"
                data-action="add-field"
            >{{translate this scope=../entityType category='fields'}}</a></li>
        {{/each}}
        </ul>
    </div>
</div>

{{/unless}}
<div>
    <div class="fields-container"></div>
</div>

</div>
</div>
