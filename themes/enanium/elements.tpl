<!-- VAR toolbar_button --><a href="{HREF}" {PARENTFLAGS} {FLAGS}>{TEXT}</a>
<!-- ENDVAR toolbar_button -->
<!-- VAR toolbar_label --><div class="label">{TEXT}</div>
<!-- ENDVAR toolbar_label -->
<!-- VAR toolbar_button_selected --><a href="{HREF}" class="current" {PARENTFLAGS} {FLAGS}>{TEXT}</a>
<!-- ENDVAR toolbar_button_selected -->
<!-- VAR toolbar_menu_button --><li><a href="{HREF}" {FLAGS}>{TEXT}</a></li>
<!-- ENDVAR toolbar_menu_button -->
<!-- VAR toolbar_menu_block --><li>{HTML}</li>
<!-- ENDVAR toolbar_menu_block -->
<!-- VAR sidebar_button --><li><a href="{HREF}" {FLAGS}>{TEXT}</a></li>
<!-- ENDVAR sidebar_button -->
<!-- VAR sidebar_raw -->{HTML}
<!-- ENDVAR sidebar_raw -->
<!-- VAR sidebar_heading --><h4>{TEXT}</h4>
<!-- ENDVAR sidebar_heading -->
<!-- VAR sidebar_top -->
<!-- ENDVAR sidebar_top -->
<!-- VAR sidebar_section -->
          <div class="slider">
            <h4>
              <!-- BEGIN in_sidebar_admin -->{ADMIN_START}<!-- END in_sidebar_admin -->
              <a>{TITLE}</a>
              <!-- BEGIN in_sidebar_admin -->{ADMIN_END}<!-- END in_sidebar_admin -->
            </h4>
            <div class="slideblock">
              <ul class="linkblock">
                {CONTENT}
              </ul>
            </div>
          </div>
<!-- ENDVAR sidebar_section -->
<!-- VAR sidebar_section_raw -->
          <div class="slider">
            <h4>
              <!-- BEGIN in_sidebar_admin -->{ADMIN_START}<!-- END in_sidebar_admin -->
              <a>{TITLE}</a>
              <!-- BEGIN in_sidebar_admin -->{ADMIN_END}<!-- END in_sidebar_admin -->
            </h4>
            <div class="slideblock">
              {CONTENT}
            </div>
          </div>
<!-- ENDVAR sidebar_section_raw -->
<!-- VAR sidebar_bottom -->
<!-- ENDVAR sidebar_bottom -->
