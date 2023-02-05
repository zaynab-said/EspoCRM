<table style="width: 100%;">
{{#each yArray}}
<tr>
    {{#each ../xArray}}
    <td id="{{this}}-{{#ifEqual ../this '[object Object]'}}{{this}}{{else}}{{../this}}{{/ifEqual}}" class="myCell" data-action="changeValue" >
        {{this}},{{#ifEqual ../this '[object Object]'}}{{this}}{{else}}{{../this}}{{/ifEqual}}
    </td>
    {{/each}}
</tr>
{{/each}}
</table>