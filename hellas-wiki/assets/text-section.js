(function (blocks, element, blockEditor, components, i18n) {
    const { Fragment } = element;
    const { RichText, InspectorControls } = blockEditor;
    const { PanelBody, TextControl, SelectControl } = components;

    const contexts = [
        { label: 'Move', value: 'move' },
        { label: 'Ability', value: 'ability' },
        { label: 'Item', value: 'item' },
        { label: 'Location', value: 'location' },
        { label: 'Guide', value: 'guide' },
        { label: 'Species', value: 'species' },
        { label: 'Generic', value: 'generic' }
    ];

    blocks.registerBlockType('hellas-wiki/text-section', {
        title: i18n.__('Hellas: Text Section', 'hellas-wiki'),
        icon: 'feedback',
        category: 'widgets',
        attributes: {
            title: { type: 'string', default: '' },
            content: { type: 'string', default: '' },
            context: { type: 'string', default: 'generic' }
        },
        supports: {
            html: false,
        },
        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { title, content, context } = attributes;

            return element.createElement(
                Fragment,
                {},
                element.createElement(
                    InspectorControls,
                    {},
                    element.createElement(
                        PanelBody,
                        { title: i18n.__('Section Settings', 'hellas-wiki'), initialOpen: true },
                        element.createElement(SelectControl, {
                            label: i18n.__('Context', 'hellas-wiki'),
                            value: context || 'generic',
                            options: contexts,
                            onChange: function (value) {
                                setAttributes({ context: value });
                            }
                        })
                    )
                ),
                element.createElement(TextControl, {
                    label: i18n.__('Title', 'hellas-wiki'),
                    value: title,
                    onChange: function (value) {
                        setAttributes({ title: value });
                    }
                }),
                element.createElement(RichText, {
                    tagName: 'div',
                    className: 'hellaswiki-text-section__editor',
                    value: content,
                    placeholder: i18n.__('Write notes…', 'hellas-wiki'),
                    onChange: function (value) {
                        setAttributes({ content: value });
                    }
                })
            );
        },
        save: function () {
            return null;
        }
    });

    const variations = [
        { name: 'move', title: i18n.__('Hellas: Move Notes', 'hellas-wiki'), attributes: { context: 'move' } },
        { name: 'ability', title: i18n.__('Hellas: Ability Notes', 'hellas-wiki'), attributes: { context: 'ability' } },
        { name: 'item', title: i18n.__('Hellas: Item Notes', 'hellas-wiki'), attributes: { context: 'item' } },
        { name: 'location', title: i18n.__('Hellas: Location Notes', 'hellas-wiki'), attributes: { context: 'location' } },
        { name: 'guide', title: i18n.__('Hellas: Guide Section', 'hellas-wiki'), attributes: { context: 'guide' } },
        { name: 'species', title: i18n.__('Hellas: Species Notes', 'hellas-wiki'), attributes: { context: 'species' } }
    ];

    variations.forEach(function (variation) {
        blocks.registerBlockVariation('hellas-wiki/text-section', {
            name: variation.name,
            title: variation.title,
            attributes: variation.attributes,
            scope: ['inserter', 'block'],
        });
    });
})(window.wp.blocks, window.wp.element, window.wp.blockEditor || window.wp.editor, window.wp.components, window.wp.i18n);
