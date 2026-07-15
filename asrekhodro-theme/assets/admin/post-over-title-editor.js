(function (wp) {
  'use strict';

  if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.components || !wp.coreData) {
    return;
  }

  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var registerPlugin = wp.plugins.registerPlugin;
  var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
  var TextControl = wp.components.TextControl;
  var useSelect = wp.data.useSelect;
  var useEntityProp = wp.coreData.useEntityProp;
  var cfg = window.akPostOverTitle || {};
  var META_KEY = cfg.metaKey || '_asrekhodro_over_title';

  function OverTitlePanel() {
    var postType = useSelect(function (select) {
      return select('core/editor').getCurrentPostType();
    }, []);

    if (postType !== 'post') {
      return null;
    }

    var metaState = useEntityProp('postType', postType, 'meta');
    var meta = metaState[0] || {};
    var setMeta = metaState[1];
    var value = typeof meta[META_KEY] === 'string' ? meta[META_KEY] : '';

    return el(
      PluginDocumentSettingPanel,
      {
        name: 'ak-post-over-title',
        title: cfg.panelTitle || 'روتیتر',
        className: 'ak-post-over-title-panel',
      },
      el(TextControl, {
        label: cfg.fieldLabel || 'روتیتر',
        value: value,
        placeholder: cfg.placeholder || '',
        help: cfg.help || '',
        onChange: function (next) {
          var nextMeta = Object.assign({}, meta);
          nextMeta[META_KEY] = typeof next === 'string' ? next : '';
          setMeta(nextMeta);
        },
      })
    );
  }

  registerPlugin('ak-post-over-title', {
    render: function () {
      return el(Fragment, null, el(OverTitlePanel));
    },
  });
})(window.wp);
