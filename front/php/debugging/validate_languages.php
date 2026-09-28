<?php
require_once __DIR__ . '/debug-layout.php';
$debugLanguage = $pia_lang;
$codes = array(
    'de_de', 'en_us', 'es_es', 'fr_fr', 'it_it', 'pl_pl', 'nl_nl', 'cz_cs',
    'fi_fi', 'lt_lt', 'dk_da', 'no_no', 'ru_ru', 'se_sv', 'ua_uk'
);
$loadLanguage = static function (string $code): array {
    $pia_lang = $pia_journ_lang = array();
    require __DIR__ . '/../language/' . $code . '.php';
    return array($pia_lang, $pia_journ_lang);
};
$languages = array();
$allMain = $allJournal = array();
foreach ($codes as $code) {
    [$main, $journal] = $loadLanguage($code);
    $languages[$code] = array(
        'name' => (string) ($debugLanguage['V4_Language_' . $code] ?? $code),
        'main' => $main,
        'journal' => $journal,
    );
    $allMain = array_merge($allMain, array_keys($main));
    $allJournal = array_merge($allJournal, array_keys($journal));
}
$allMain = array_values(array_unique($allMain));
$allJournal = array_values(array_unique($allJournal));
sort($allMain, SORT_STRING);
sort($allJournal, SORT_STRING);
$missingTotal = 0;
$englishFallbackTotal = 0;
foreach ($languages as &$language) {
    $language['missingMain'] = array_values(array_diff($allMain, array_keys($language['main'])));
    $language['missingJournal'] = array_values(array_diff($allJournal, array_keys($language['journal'])));
    $missingTotal += count($language['missingMain']) + count($language['missingJournal']);
}
unset($language);
foreach ($languages as $code => &$language) {
    $source = (string) file_get_contents(__DIR__ . '/../language/' . $code . '.php');
    preg_match_all('/^\$pia_lang\[\x27([^\x27]+)\x27\]\s*=.*;\s*\/\/ @english-fallback\s*$/m', $source, $matches);
    $language['fallbackKeys'] = array_values(array_unique($matches[1]));
    $englishFallbackTotal += count($language['fallbackKeys']);
}
unset($language);
pialert_debug_start(
    pialert_debug_label('V4_Compare_Languages', 'Compare language arrays'),
    'languages',
    pialert_debug_label('V4_Debug_Languages_Intro', 'Compare translation keys and review values still shown in English.')
);
?>
<section class="card mb-3">
  <div class="card-header"><h2 class="card-title"><?= h(pialert_debug_label('V4_Results', 'Results')); ?></h2></div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-6 col-lg-3"><div class="debug-stat"><span class="debug-stat-label"><?= h(pialert_debug_label('V4_Debug_Languages', 'Languages')); ?></span><span class="debug-stat-value"><?= count($languages); ?></span></div></div>
      <div class="col-6 col-lg-3"><div class="debug-stat"><span class="debug-stat-label"><?= h(pialert_debug_label('V4_Entry_Count', 'Entry count')); ?></span><span class="debug-stat-value"><?= count($allMain) + count($allJournal); ?></span></div></div>
      <div class="col-6 col-lg-3"><div class="debug-stat"><span class="debug-stat-label"><?= h(pialert_debug_label('V4_Missing_Entries', 'Missing entries')); ?></span><span class="debug-stat-value<?= $missingTotal ? ' text-warning' : ' text-success'; ?>"><?= $missingTotal; ?></span></div></div>
      <div class="col-6 col-lg-3"><div class="debug-stat"><span class="debug-stat-label"><?= h(pialert_debug_label('V4_Debug_English_Fallbacks', 'English placeholders')); ?></span><span class="debug-stat-value text-warning"><?= $englishFallbackTotal; ?></span></div></div>
    </div>
  </div>
</section>
<section class="card mb-3">
  <div class="card-header"><h2 class="card-title"><?= h(pialert_debug_label('V4_Entry_Count', 'Entry count')); ?></h2></div>
  <div class="card-body"><div class="debug-language-list">
    <?php foreach ($languages as $code => $language): ?>
      <div class="debug-language-count"><div class="fw-semibold mb-2"><?= h($language['name']); ?> <span class="text-body-secondary small"><?= h($code); ?></span></div><div class="d-flex justify-content-between gap-2"><span>Pi.Alert</span><strong><?= count($language['main']); ?></strong></div><div class="d-flex justify-content-between gap-2"><span>Journal</span><strong><?= count($language['journal']); ?></strong></div><div class="d-flex justify-content-between gap-2 text-warning"><span><?= h(pialert_debug_label('V4_Debug_English_Fallbacks', 'English placeholders')); ?></span><strong><?= count($language['fallbackKeys']); ?></strong></div></div>
    <?php endforeach; ?>
  </div></div>
</section>
<section class="card mb-3">
  <div class="card-header"><h2 class="card-title"><?= h(pialert_debug_label('V4_Missing_Entries', 'Missing entries')); ?></h2></div>
  <div class="card-body">
    <?php if ($missingTotal === 0): ?>
      <p class="alert alert-success mb-0"><?= h(pialert_debug_label('V4_Debug_All_Keys_Present', 'All language keys are present.')); ?></p>
    <?php else: ?>
    <div class="debug-toolbar mb-3">
      <div class="debug-search"><label class="form-label" for="languageSearch"><?= h(pialert_debug_label('V4_Table_Search', 'Search')); ?></label><input class="form-control" type="search" id="languageSearch" placeholder="<?= h(pialert_debug_label('V4_Debug_Search_Languages', 'Search languages or keys')); ?>"></div>
      <label class="form-check mb-2"><input class="form-check-input" type="checkbox" id="onlyMissing"><span class="form-check-label"><?= h(pialert_debug_label('V4_Debug_Only_Missing', 'Only languages with missing keys')); ?></span></label>
    </div>
    <div class="row g-3">
      <?php foreach (array('main' => array('Pi.Alert', 'missingMain'), 'journal' => array('Journal', 'missingJournal')) as $group => [$title, $missingKey]): ?>
      <div class="col-12 col-xl-6"><h3 class="h6 mb-3"><?= h($title); ?></h3><div class="d-grid gap-2">
        <?php foreach ($languages as $code => $language): $keys = $language[$missingKey]; ?>
          <details class="debug-detail-item debug-missing-item" data-missing-count="<?= count($keys); ?>">
            <summary><span class="fw-semibold"><?= h($language['name']); ?></span><span class="small text-body-secondary"><?= h($code); ?></span><span class="badge <?= $keys ? 'text-bg-warning' : 'text-bg-success'; ?> ms-auto"><?= count($keys); ?></span></summary>
            <div class="debug-missing-keys"><?= $keys ? h(implode(', ', $keys)) : h(pialert_debug_label('V4_Debug_No_Missing', 'No missing keys')); ?></div>
          </details>
        <?php endforeach; ?>
      </div></div>
      <?php endforeach; ?>
    </div>
    <p class="small text-body-secondary mt-3 mb-0" id="languageNoMatches" hidden><?= h(pialert_debug_label('V4_Zero_Records', 'No matching records found')); ?></p>
    <?php endif; ?>
  </div>
</section>
<section class="card mb-3">
  <div class="card-header"><h2 class="card-title"><?= h(pialert_debug_label('V4_Debug_English_Fallbacks', 'English placeholders')); ?></h2><span class="badge text-bg-warning ms-auto"><?= $englishFallbackTotal; ?></span></div>
  <div class="card-body">
    <p class="text-body-secondary mb-3"><?= h(pialert_debug_label('V4_Debug_Fallback_Intro', 'These keys exist, but their values still use English. Replace the marked values in the language files to complete the translations.')); ?></p>
    <div class="debug-toolbar mb-3"><div class="debug-search"><label class="form-label" for="fallbackSearch"><?= h(pialert_debug_label('V4_Table_Search', 'Search')); ?></label><input class="form-control" type="search" id="fallbackSearch" placeholder="<?= h(pialert_debug_label('V4_Debug_Search_Languages', 'Search languages or keys')); ?>"></div></div>
    <div class="row g-3"><div class="col-12"><div class="d-grid gap-2">
      <?php foreach ($languages as $code => $language): if (!$language['fallbackKeys']) continue; ?>
        <details class="debug-detail-item debug-fallback-item">
          <summary><span class="fw-semibold"><?= h($language['name']); ?></span><span class="small text-body-secondary"><?= h($code); ?></span><span class="badge text-bg-warning ms-auto"><?= count($language['fallbackKeys']); ?></span></summary>
          <div class="debug-missing-keys"><?= h(implode(', ', $language['fallbackKeys'])); ?></div>
        </details>
      <?php endforeach; ?>
    </div></div></div>
    <p class="small text-body-secondary mt-3 mb-0" id="fallbackNoMatches" hidden><?= h(pialert_debug_label('V4_Zero_Records', 'No matching records found')); ?></p>
  </div>
</section>
<script>
  const languageSearch = document.getElementById('languageSearch');
  const onlyMissing = document.getElementById('onlyMissing');
  function filterLanguages() {
    const query = languageSearch.value.trim().toLocaleLowerCase();
    let visible = 0;
    document.querySelectorAll('.debug-missing-item').forEach(item => {
      const summaryMatches = item.querySelector('summary').textContent.toLocaleLowerCase().includes(query);
      const matches = !query || item.textContent.toLocaleLowerCase().includes(query);
      item.hidden = (onlyMissing.checked && Number(item.dataset.missingCount) === 0) || !matches;
      if (!item.hidden) visible++;
      if (query && matches && !summaryMatches) item.open = true;
    });
    document.getElementById('languageNoMatches').hidden = visible !== 0;
  }
  if (languageSearch && onlyMissing) {
    languageSearch.addEventListener('input', filterLanguages);
    onlyMissing.addEventListener('change', filterLanguages);
  }
  const fallbackSearch = document.getElementById('fallbackSearch');
  fallbackSearch.addEventListener('input', function () {
    const query = fallbackSearch.value.trim().toLocaleLowerCase();
    let visible = 0;
    document.querySelectorAll('.debug-fallback-item').forEach(item => {
      const summaryMatches = item.querySelector('summary').textContent.toLocaleLowerCase().includes(query);
      const matches = !query || item.textContent.toLocaleLowerCase().includes(query);
      item.hidden = !matches;
      if (!item.hidden) visible++;
      if (query && matches && !summaryMatches) item.open = true;
    });
    document.getElementById('fallbackNoMatches').hidden = visible !== 0;
  });
</script>
<?php
$pia_lang = $debugLanguage;
pialert_debug_end();
?>
