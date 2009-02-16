<!-- Stuff related to toolbars and clickable buttons.
     The plan was to use this on the toolbar for most pages. Never made it into the release,
     but still provided as an otherwise-unused component for plugins to make use of.
     -->

<!-- VAR toolbar_start -->
  <div class="toolbar">
  <ul>
<!-- ENDVAR toolbar_start -->
<!-- VAR toolbar_button -->
  <li>
    <a title="{TITLE}" {FLAGS}>
      <!-- IFSET SPRITE -->
        {SPRITE}
      <!-- BEGINELSE SPRITE -->
        <!-- IFSET IMAGE -->
          <!-- BEGINNOT no_image -->
            <img alt="{TITLE}" src="{IMAGE}" />
          <!-- END no_image -->
        <!-- END IMAGE -->
      <!-- END SPRITE -->
      <!-- BEGIN show_title -->
        <!-- BEGIN no_image -->
          <span class="noimage">{TITLE}</span>
        <!-- BEGINELSE no_image -->
          <span>{TITLE}</span>
        <!-- END no_image -->
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
