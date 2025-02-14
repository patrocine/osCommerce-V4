<div class="xl-pr-box" id="box-xl-pr">
    <div class="after">
        <div class="attr-box attr-box-1">
            <div class="widget widget-attr-box box box-no-shadow" style="margin-bottom: 0;">
                <div class="widget-header">
                    <h4>{$smarty.const.TEXT_FIND_PAGES}</h4>
                    <div class="box-head-serch after">
                        <input type="search" id="searchInfo" placeholder="{$smarty.const.SEARCH_BY_INFORMATION}" class="form-control">
                        <button onclick="return false"></button>
                    </div>
                </div>
                <div class="widget-content">
                    <select id="selectSearchInfopage" size="25" style="width: 100%; height: 100%; border: none;" ondblclick="addSelectedInfoPage()" multiple="multiple">
                    </select>
                </div>
            </div>
        </div>
        <div class="attr-box attr-box-2">
            <span class="btn btn-primary" onclick="addSelectedInfoPage()"></span>
        </div>
        <div class="attr-box attr-box-3">
            <div class="widget-new widget-attr-box box box-no-shadow" style="margin-bottom: 0;">
                <div class="widget-header">
                    <h4>Assigned Pages</h4>
                    {*<div class="box-head-serch after">
                        <input type="search" id="searchAssignedInfo" placeholder="{$smarty.const.SEARCH_BY_INFORMATION}" class="form-control">
                        <button onclick="return false"></button>
                    </div>*}
                </div>
                <div class="widget-content">
                    <table class="table assig-attr-sub-table set-assigned-info-pages">
                        <thead>
                        <tr role="row">
                            <th>{$smarty.const.TABLE_TEXT_NAME}</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>

                        {foreach $crossPages as $crossPage}
                            <tr>
                                <td class="ast-name-element">
                                    {$crossPage.text}
                                    <input type="hidden" name="selectedPages[]" value="{$crossPage.id}">
                                </td>
                                <td class="remove-ast" onclick="deleteSelectedInfoPage(this)"></td>
                            </tr>
                        {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    function addSelectedInfoPage() {
        $('#selectSearchInfopage option:selected').each(function() {
            var information_id = $(this).val();
            var text = $(this).text();
            if ( $('input[name="AssignInformationForm['+information_id+'][information_id]"]').length ) {
                //already exist
            } else {
                $( ".set-assigned-info-pages tbody" ).append(`
                <tr>
                    <td class="ast-name-element">${ text}<input type="hidden" name="selectedPages[]" value="${ information_id}"></td>
                    <td class="remove-ast" onclick="deleteSelectedInfoPage(this)"></td>
                </tr>
                `);
            }
        });

        return false;
    }

    function deleteSelectedInfoPage(obj) {
        $(obj).parent().remove();
        return false;
    }

    var color = '#ff0000';
    var phighlight = function(obj, reg){
        if (reg.length == 0) return;
        $(obj).html($(obj).text().replace( new RegExp( "(" +  reg  + ")" , 'gi' ), '<font style="color:'+color+'">$1</font>'));
        return;
    }

    var searchHighlightExisting = function(e){
        var $rows = $(e.data.rows_selector);
        var search_term = $(this).val();
        $rows.each(function(){
            var $row = $(this);
            var $value_text = $row.find(e.data.text_selector);
            var search_match = true;

            if ( !$row.data('raw-value') ) $row.data('raw-value', $value_text.html());
            var prop_value = $row.data('raw-value');
            if ( search_term.length>0 ) {
                var searchRe = new RegExp(".*" + (search_term + "").replace(/([.?*+\^\$\[\]\\(){}|-])/g, "\\$1") + ".*", 'i');
                if (searchRe.test(prop_value)) {
                    phighlight($value_text, search_term);
                } else {
                    $value_text.html(prop_value);
                    search_match = false;
                }
            }else{
                $value_text.html(prop_value);
            }

            if ( search_match ) {
                $row.show();
            }else{
                $row.hide();
            }
        });
    };
    $(document).ready(function() {
        var infoPageList = $( "select#selectSearchInfopage" );
        $('#searchAssignedInfo').on('focus keyup', { rows_selector: '.set-assigned-info-pages tbody tr', text_selector: '.ast-name-element'}, searchHighlightExisting);

        $('#searchInfo').on('focus keyup', function(e) {
            var str = $(this).val();
            $.post( "{Yii::$app->urlManager->createUrl('seo-delivery-location/get-pages')}"
                ,{
                    'platform_id':{$platform_id},
                    'term':encodeURIComponent(str),
                }, function( data ) {
                    infoPageList.html( data );
                    psearch = new RegExp(str, 'i');
                    $.each(infoPageList.find('option'), function(i, e){
                        if (psearch.test($(e).text())){
                            phighlight(e, str);
                        }
                    });
                });
        }).keyup();
    });
</script>
