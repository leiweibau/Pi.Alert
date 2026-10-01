<?php
function pialert_entity_actions_editor(callable $L): void {
    $labels = array(
        'empty' => $L('EntityActions_Empty','No links configured yet.'),
        'url' => $L('EntityActions_URL','URL'),
        'label' => $L('EntityActions_Label','Label (optional)'),
        'icon' => $L('EntityActions_Icon','Choose icon'),
        'color' => $L('EntityActions_Color','Button color'),
        'textColor' => $L('EntityActions_TextColor','Text color'),
        'remove' => $L('EntityActions_Remove','Remove link'),
        'add' => $L('EntityActions_Add','Add link'),
        'save' => $L('Gen_Save','Save'),
        'reset' => $L('DevDetail_button_Reset','Reset'),
        'search' => $L('EntityActions_Search','Search icons'),
        'allFamilies' => $L('EntityActions_AllFamilies','All libraries'),
        'loading' => $L('EntityActions_Loading','Loading links…'),
        'saved' => $L('EntityActions_Saved','Links saved.'),
        'schema_unavailable' => $L('EntityActions_SchemaUnavailable','Database update pending. Links cannot be edited yet.'),
        'target_not_found' => $L('EntityActions_TargetNotFound','This device or host no longer exists.'),
        'invalid_target' => $L('EntityActions_TargetNotFound','This device or host no longer exists.'),
        'invalid_actions' => $L('EntityActions_InvalidActions','The link list is invalid.'),
        'too_many_actions' => $L('EntityActions_TooMany','Only three links are allowed.'),
        'invalid_url' => $L('EntityActions_InvalidURL','Enter a complete HTTP or HTTPS URL without credentials.'),
        'invalid_icon' => $L('EntityActions_InvalidIcon','Choose an available icon.'),
        'invalid_color' => $L('EntityActions_InvalidColor','Choose a valid six-digit color.'),
        'invalid_text_color' => $L('EntityActions_InvalidTextColor','Choose a valid six-digit text color.'),
        'invalid_label' => $L('EntityActions_InvalidLabel','The label must be at most 80 characters.'),
        'foreign_action' => $L('EntityActions_ForeignAction','This link no longer belongs to the current item.'),
        'conflict' => $L('EntityActions_Conflict','Links changed elsewhere. Reset to load the latest version.'),
        'save_failed' => $L('EntityActions_SaveFailed','Links could not be saved.'),
        'csrf_invalid' => $L('EntityActions_SaveFailed','Links could not be saved.'),
        'auth_required' => $L('EntityActions_SaveFailed','Links could not be saved.'),
        'leave' => $L('EntityActions_Leave','Discard unsaved links and continue?'),
        'discard' => $L('EntityActions_Discard','Discard'),
        'cancel' => $L('Gen_Cancel','Cancel'),
        'close' => $L('Gen_Close','Close'),
        'invalidLocal' => $L('EntityActions_InvalidURL','Enter a complete HTTP or HTTPS URL without credentials.'),
    );
    ?>
    <div class="entity-actions-editor" id="entity-actions-editor" data-labels="<?= h(json_encode($labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>">
      <p class="text-body-secondary"><?= h($L('EntityActions_Hint','Add up to three links. They open in a new tab.')); ?></p>
      <div class="entity-actions-message" role="status" aria-live="polite"></div>
      <div class="entity-actions-rows"></div>
      <div class="entity-actions-toolbar"><button class="btn btn-outline-primary entity-actions-add" type="button"><i class="fa-solid fa-plus" aria-hidden="true"></i> <?= h($labels['add']); ?></button><div class="entity-actions-save-controls"><button class="btn btn-outline-secondary entity-actions-reset" type="button" disabled><?= h($labels['reset']); ?></button><button class="btn btn-primary entity-actions-save" type="button" disabled><?= h($labels['save']); ?></button></div></div>
      <div class="modal fade" id="entity-actions-icon-modal" tabindex="-1" aria-labelledby="entity-actions-icon-title" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable modal-lg"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="entity-actions-icon-title"><?= h($labels['icon']); ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= h($L('Gen_Close','Close')); ?>"></button></div><div class="modal-body"><div class="entity-actions-icon-filters"><label class="visually-hidden" for="entity-actions-icon-search"><?= h($labels['search']); ?></label><input id="entity-actions-icon-search" class="form-control" type="search" placeholder="<?= h($labels['search']); ?>"><label class="visually-hidden" for="entity-actions-icon-family"><?= h($labels['allFamilies']); ?></label><select id="entity-actions-icon-family" class="form-select"><option value=""><?= h($labels['allFamilies']); ?></option></select></div><div class="entity-actions-icon-grid"></div><button class="btn btn-outline-secondary entity-actions-more" type="button" hidden><?= h($L('EntityActions_More','Show more')); ?></button></div></div></div></div>
      <div class="modal fade" id="entity-actions-leave-modal" tabindex="-1" aria-labelledby="entity-actions-leave-title" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="entity-actions-leave-title"><?= h($L('EntityActions_Unsaved','Unsaved links')); ?></h2></div><div class="modal-body"><?= h($labels['leave']); ?></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-choice="cancel"><?= h($labels['cancel']); ?></button><button type="button" class="btn btn-outline-danger" data-choice="discard"><?= h($labels['discard']); ?></button><button type="button" class="btn btn-primary" data-choice="save"><?= h($labels['save']); ?></button></div></div></div></div>
    </div>
    <?php
}
