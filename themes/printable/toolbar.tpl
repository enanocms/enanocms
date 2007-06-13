<!-- Stuff related to toolbars and clickable buttons.
     Used mostly in the page toolbar on most pages.
     -->

<!-- VAR toolbar_start -->
  <div class="toolbar">
  <ul>
<!-- ENDVAR toolbar_start -->
<!-- VAR toolbar_button -->
  <li>
    <a title="{TITLE}" {FLAGS}>
      <img alt="{TITLE}" src="{IMAGE}" />
      <!-- BEGIN show_title -->
      <span>{TITLE}</span>
      <!-- END show_title -->
    </a>
  </li>
<!-- ENDVAR toolbar_button -->
<!-- VAR toolbar_label -->
  <li>
    <span>{TITLE}</span>
  </li>
<!-- ENDVAR toolbar_label -->
<!-- VAR toolbar_end -->
  </ul>
  </div>
<!-- ENDVAR toolbar_end -->

<!-- VAR toolbar_vert_start -->
  <div class="toolbar_vert">
  <ul>
<!-- ENDVAR toolbar_vert_start -->
<!-- VAR toolbar_vert_button -->
  <li>
    <a title="{TITLE}" {FLAGS}>
      <img alt="{TITLE}" src="{IMAGE}" />
      <span>{TITLE}</span>
    </a>
  </li>
<!-- ENDVAR toolbar_vert_button -->
<!-- VAR toolbar_vert_label -->
  <li>
    <span>{TITLE}</span>
  </li>
<!-- ENDVAR toolbar_vert_label -->
<!-- VAR toolbar_vert_end -->
  </ul>
  </div>
<!-- ENDVAR toolbar_vert_end -->
