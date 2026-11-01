{{-- Team and project colours for the searchable selects' colour dots
     (resources/js/searchable-select.js reads #ss-swatches). Ids → hex only. --}}
<script type="application/json" id="ss-swatches">@json(['teams' => $ssTeamColors, 'projects' => $ssProjectColors])</script>
