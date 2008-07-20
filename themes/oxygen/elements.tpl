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
<!-- VAR sidebar_button --><a href="{HREF}" {FLAGS}>{TEXT}</a><br style="display: none;" />
<!-- ENDVAR sidebar_button -->
<!-- VAR sidebar_raw --><span style="text-align: center;">{HTML}</span><br style="display: none;" />
<!-- ENDVAR sidebar_raw -->
<!-- VAR sidebar_heading --><div class="heading">{TEXT}</div>
<!-- ENDVAR sidebar_heading -->
<!-- VAR sidebar_top -->
          <div class="recttop">
            <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 1px;">
              <tr>
                <td style="margin: 0; padding: 0; height: 12px;"> <img alt=" " src="{CDNPATH}/images/spacer.gif" style="background-image: url({CDNPATH}/themes/oxygen/images/{STYLE_ID}/sprite-horiz.gif); background-position: 0 0; background-repeat: no-repeat;" width="12" height="12" /> </td>
                <td style="margin: 0; padding: 0; height: 12px;" class="recttoptop" onclick="var id = this.parentNode.parentNode.parentNode.parentNode.parentNode.id; var side = id.substr(0, id.indexOf('-')); collapseSidebar(side);"></td>
                <td style="margin: 0; padding: 0; height: 12px;"> <img alt=" " src="{CDNPATH}/images/spacer.gif" style="background-image: url({CDNPATH}/themes/oxygen/images/{STYLE_ID}/sprite-horiz.gif); background-position: -12px 0; background-repeat: no-repeat;" width="12" height="12" /> </td>
              </tr>
            </table>
          </div>
          <div class="sidebar">
<!-- ENDVAR sidebar_top -->
<!-- VAR sidebar_section -->
            <div class="slider">
              <div class="heading">
                <!-- BEGIN in_sidebar_admin -->{ADMIN_START}<!-- END in_sidebar_admin -->
                <br style="display: none;" /><br style="display: none;" />
                <a class="head" onclick="toggle(this); return false" href="#">{TITLE}</a>
                <!-- BEGIN in_sidebar_admin -->{ADMIN_END}<!-- END in_sidebar_admin -->
                <br style="display: none;" /><br style="display: none;" />
              </div>
              <div class="slideblock">{CONTENT}</div>
            </div>
<!-- ENDVAR sidebar_section -->
<!-- VAR sidebar_section_raw -->
            <div class="slider">
              <div class="heading">
                <!-- BEGIN in_sidebar_admin -->{ADMIN_START}<!-- END in_sidebar_admin -->
                <br style="display: none;" /><br style="display: none;" />
                <a class="head" onclick="toggle(this); return false" href="#">{TITLE}</a>
                <!-- BEGIN in_sidebar_admin -->{ADMIN_END}<!-- END in_sidebar_admin -->
                <br style="display: none;" /><br style="display: none;" />
              </div>
              <div class="slideblock2">{CONTENT}</div>
            </div>
<!-- ENDVAR sidebar_section_raw -->
<!-- VAR sidebar_bottom -->
          </div>
          <div class="rectbot">
            <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 1px;">
              <tr>
                <td style="margin: 0; padding: 0; height: 12px;"> <img alt=" " src="{CDNPATH}/images/spacer.gif" style="background-image: url({CDNPATH}/themes/oxygen/images/{STYLE_ID}/sprite-horiz.gif); background-position: -24px 0; background-repeat: no-repeat;" width="12" height="12" /> </td>
                <td style="margin: 0; padding: 0; height: 12px;" class="rectbottop"></td>
                <td style="margin: 0; padding: 0; height: 12px;"> <img alt=" " src="{CDNPATH}/images/spacer.gif" style="background-image: url({CDNPATH}/themes/oxygen/images/{STYLE_ID}/sprite-horiz.gif); background-position: -36px 0; background-repeat: no-repeat;" width="12" height="12" /> </td>
              </tr>
            </table>
          </div>
<!-- ENDVAR sidebar_bottom -->
