import createUrl from "src/createUrl";
import openEditDataPopup from "./openEditDataPopup";
import store from "./store";

export default {
    iframeSettings(element) {
        let url = store.data.DIR_WS_HTTP_ADMIN_CATALOG
        if (url.substring(0, 4) !== 'http') {
            const splitUrl = window.location.href.split(':');
            url = splitUrl[0] + ':' + url;
        }
        return {
            id: 'translation-frame',
            src: createUrl(url + '/texts/edit', {
                translation_key: $(element).data('key'),
                translation_entity: $(element).data('entity'),
                popup: 1
            }),
        }
    },

    frameScripts(frame, popup){
        $('.btn-confirm', frame).on('click', function(){
            setTimeout(function(){
                popup.remove();
                $(window).trigger('reload-frame')
            }, 100)
        });
        $('.btn-cancel-foot', frame).on('click', function(){
            setTimeout(function(){
                popup.remove();
                $.get(store.data.setFrontendTranslationTimeUrl)
            }, 100)
        });
    },

    hints(hintPosition){
        let frame = $('#info-view').contents();
        let _this = this;
        $('.translation-key-option', frame).each(function(){
            let select = $(this).closest('select')
            select.attr('data-translation', '');

            let value = $(this).attr('value');
            let key = $(this).data('translation-key');
            let entity = $(this).data('translation-entity');

            select.attr('data-translation-key-' + value, key);
            select.attr('data-translation-entity-' + value, entity);
        })

        $('*[data-translation], .translation-key', frame).off('mouseenter').on('mouseenter', function(){
            store.data.$editButtons.html('');
            let element = $(this)
            $.each(this.attributes, function() {
                if (this.name.search('data-translation-key') === 0) {
                    let type = this.name.replace('data-translation-key-', '');
                    let key = this.value;
                    let entity = element.data('translation-entity-' + type);
                    if (!entity) entity = element.data('translation-entity');
                    let editBtn = $(`<div class="translation-edit-button" data-key="${key}" data-entity="${entity}">${key}</div>`);
                    store.data.$editButtons.append(editBtn);

                    editBtn.on('click', function(){
                        openEditDataPopup(_this.iframeSettings(this), _this.frameScripts, {heading: 'Translate'})
                    })
                }
            });
            hintPosition(element)
        });
    }
}