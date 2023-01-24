{{#unless optionList}}
    {{translate 'No Data'}}
{{/unless}}
<ul class="list-group list-group-panel array-add-list-group no-side-margin">
{{#each optionList}}
    <li class="list-group-item clearfix">
        <input
            class="cell form-checkbox form-checkbox-small"
            type="checkbox"
            data-value="{{./this}}"
        >
        <a role="button" tabindex="0" class="add text-bold" data-value="{{./this}}">
            {{#if ../translatedOptions}}{{prop ../translatedOptions this}}{{else}}{{./this}}{{/if}}
        </a>
    </li>
{{/each}}
</ul>
