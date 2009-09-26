          </div> <!-- div#ajaxEditContainer -->
          </td>
          <!-- BEGIN right_sidebar -->
          <td valign="top" class="td-right-sidebar">
            <div class="right sidebar" id="enanium_sidebar_right">
              <a class="closebtn" onclick="enanium_toggle_sidebar_right(); return false;">&raquo;</a>
              {SIDEBAR_RIGHT}
            </div>
            <div class="right-sidebar-hidden" id="enanium_sidebar_right_hidden">
              <a class="openbtn" onclick="enanium_toggle_sidebar_right(); return false;">&laquo;</a>
            </div>
            <!-- HOOK sidebar_right_post -->
          </td>
          <!-- END right_sidebar -->
          </tr>
          </table>
        </div> <!-- div#content-wrapper -->
      </td>
    </tr>
    </table>
    <div id="footer">
      <b>{COPYRIGHT}</b><br />
      <!-- You may remove the following line, but it will affect your support from the Enano project. See: http://enanocms.org/powered-link -->
      [[EnanoPoweredLinkLong]]&nbsp;&nbsp;|&nbsp;&nbsp;<!-- BEGINNOT stupid_mode --><a href="http://validator.w3.org/check?uri=referer">{lang:page_w3c_valid_xhtml11}</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="http://jigsaw.w3.org/css-validator/validator?uri=referer">{lang:page_w3c_valid_css}</a>&nbsp;&nbsp;|&nbsp;&nbsp;<!-- END stupid_mode -->[[StatsLong]]
      <!-- Do not remove this line or scheduled tasks will not run. -->
      <img alt=" " src="{SCRIPTPATH}/cron.php" width="1" height="1" />
    </div>
  {JS_FOOTER}
  </body>
</html>

