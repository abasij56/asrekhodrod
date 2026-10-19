(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.blockEditor || !wp.components || !wp.data || !wp.element || !wp.hooks) {
    return;
  }

  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useEffect = wp.element.useEffect;
  var SelectControl = wp.components.SelectControl;
  var useSelect = wp.data.useSelect;
  var useDispatch = wp.data.useDispatch;
  var cfg = window.akPrimaryTag || {};
  var BLOCK_NAME = cfg.blockName || 'asrekhodro/primary-tag';

  wp.blocks.registerBlockType(BLOCK_NAME, {
    apiVersion: 2,
    title: cfg.label || 'تگ اول',
    category: 'text',
    icon: 'tag',
    attributes: {
      termId: { type: 'number', default: 0 },
    },
    supports: {
      inserter: false,
      html: false,
      reusable: false,
      multiple: false,
      className: false,
      customClassName: false,
    },
    edit: function () {
      return null;
    },
    save: function () {
      return null;
    },
  });

  function findPrimaryBlock(blocks) {
    for (var i = 0; i < blocks.length; i++) {
      if (blocks[i].name === BLOCK_NAME) {
        return blocks[i];
      }
    }
    return null;
  }

  function PrimaryTagSelect() {
    var postType = useSelect(function (select) {
      return select('core/editor').getCurrentPostType();
    }, []);

    var tagIds = useSelect(function (select) {
      return select('core/editor').getEditedPostAttribute('tags') || [];
    }, []);

    var terms = useSelect(
      function (select) {
        if (!tagIds.length) {
          return [];
        }
        return (
          select('core').getEntityRecords('taxonomy', 'post_tag', {
            include: tagIds,
            per_page: 100,
            context: 'view',
            _fields: 'id,name',
          }) || []
        );
      },
      [tagIds.join(',')]
    );

    var blockClientId = useSelect(function (select) {
      var block = findPrimaryBlock(select('core/block-editor').getBlocks());
      return block ? block.clientId : '';
    }, []);

    var primaryId = useSelect(function (select) {
      var block = findPrimaryBlock(select('core/block-editor').getBlocks());
      return block ? parseInt(block.attributes.termId, 10) || 0 : 0;
    }, []);

    var blockCount = useSelect(function (select) {
      return select('core/block-editor').getBlockCount();
    }, []);

    var editor = useDispatch('core/block-editor');

    function setPrimary(termId) {
      if (!termId) {
        if (blockClientId) {
          editor.removeBlock(blockClientId, false);
        }
        return;
      }

      if (blockClientId) {
        editor.updateBlockAttributes(blockClientId, { termId: termId });
        return;
      }

      editor.insertBlocks(wp.blocks.createBlock(BLOCK_NAME, { termId: termId }), blockCount, '', false);
    }

    useEffect(
      function () {
        if (primaryId && blockClientId && tagIds.length && tagIds.indexOf(primaryId) === -1) {
          editor.removeBlock(blockClientId, false);
        }
      },
      [primaryId, blockClientId, tagIds.join(',')]
    );

    if (postType !== 'post' || !tagIds.length) {
      return null;
    }

    var options = [{ label: cfg.none || '— بدون تگ اول —', value: '0' }];
    terms.forEach(function (term) {
      options.push({ label: term.name, value: String(term.id) });
    });

    return el(
      'div',
      { className: 'ak-primary-tag-select', style: { marginTop: '16px' } },
      el(SelectControl, {
        label: cfg.label || 'تگ اول',
        value: String(primaryId),
        options: options,
        help: cfg.help || '',
        onChange: function (value) {
          setPrimary(parseInt(value, 10) || 0);
        },
        __nextHasNoMarginBottom: true,
      })
    );
  }

  wp.hooks.addFilter('editor.PostTaxonomyType', 'asrekhodro/primary-tag-select', function (OriginalComponent) {
    return function (props) {
      if (!props || props.slug !== 'post_tag') {
        return el(OriginalComponent, props);
      }

      return el(Fragment, null, el(OriginalComponent, props), el(PrimaryTagSelect));
    };
  });
})(window.wp);
