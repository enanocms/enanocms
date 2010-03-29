<?php

/**
 * XHTML diff renderer.
 *
 * This class renders diffs in XHTML format.
 *
 * $Horde: framework/Text_Diff/Diff/Renderer/inline.php,v 1.16 2006/01/08 00:06:57 jan Exp $
 *
 * @author  Ciprian Popovici
 * @author  Dan Fuhry
 * @package Text_Diff
 */
class Text_Diff_Renderer_xhtml extends Text_Diff_Renderer {

		/**
 		* Number of leading context "lines" to preserve.
 		*/
		var $_leading_context_lines = 5;

		/**
 		* Number of trailing context "lines" to preserve.
 		*/
		var $_trailing_context_lines = 3;

		/**
 		* Prefix for inserted text.
 		*/
		var $_ins_prefix = "<!-- Start added text -->\n<tr><td style='width: 0px;'>+</td><td class=\"diff-added\" style='width: 100%;'>";

		/**
 		* Suffix for inserted text.
 		*/
		var $_ins_suffix = "</td></tr>\n<!-- End added text -->\n\n";

		/**
 		* Prefix for deleted text.
 		*/
		var $_del_prefix = "<!-- Start deleted text -->\n<tr><td style='width: 0px;'>-</td><td class=\"diff-deleted\" style='width: 100%;'>";

		/**
 		* Suffix for deleted text.
 		*/
		var $_del_suffix = "</td></tr>\n<!-- End deleted text -->\n\n";

		/**
 		* Header for each change block.
 		*/
		var $_block_header = '';

		/**
 		* What are we currently splitting on? Used to recurse to show word-level
 		* changes.
 		*/
		var $_split_level = 'lines';
		
		function _blockHeader($xbeg, $xlen, $ybeg, $ylen)
		{
			return "<!-- Start block -->\n<tr><td colspan='2' class='diff-block'>Line $xbeg: {$this->_block_header}</td></tr>";
		}

		function _startBlock($header)
		{
				return $header;
		}

		function _lines($lines, $prefix = ' ', $encode = true)
		{
				if ($encode) {
						array_walk($lines, array(&$this, '_encode'));
				}

				if ($this->_split_level == 'words') {
						return implode('', $lines);
				} else {
						return implode("<br />", $lines) . "\n";
				}
		}

		function _added($lines)
		{
				array_walk($lines, array(&$this, '_encode'));
				$lines[0] = $this->_ins_prefix . $lines[0];
				$lines[count($lines) - 1] .= $this->_ins_suffix;
				return $this->_lines($lines, ' ', false);
		}

		function _deleted($lines, $words = false)
		{
				array_walk($lines, array(&$this, '_encode'));
				$lines[0] = $this->_del_prefix . $lines[0];
				$lines[count($lines) - 1] .= $this->_del_suffix;
				return $this->_lines($lines, ' ', false);
		}
		
		function _context($lines)
		{
				return "<!-- Start context -->\n<tr><td></td><td class=\"diff-context\">".$this->_lines($lines).'</td></tr>'."\n<!-- End context -->\n\n";
		}

		function _changed($orig, $final)
		{
				/* If we've already split on words, don't try to do so again - just display. */ 
				if ($this->_split_level == 'words') {
						$prefix = '';
						while ($orig[0] !== false && $final[0] !== false &&
 									substr($orig[0], 0, 1) == ' ' &&
 									substr($final[0], 0, 1) == ' ') {
								$prefix .= substr($orig[0], 0, 1);
								$orig[0] = substr($orig[0], 1);
								$final[0] = substr($final[0], 1);
						}
						$ret = $prefix . $this->_deleted($orig) . $this->_added($final) . "\n";
						//echo 'DEBUG:<pre>'.htmlspecialchars($ret).'</pre>';
						return $ret;
				}

				$text1 = implode("\n", $orig);
				$text2 = implode("\n", $final);

				/* Non-printing newline marker. */
				$nl = "\0";

				/* We want to split on word boundaries, but we need to
 				* preserve whitespace as well. Therefore we split on words,
 				* but include all blocks of whitespace in the wordlist. */
				$diff = &new Text_Diff($this->_splitOnWords($text1, $nl),
 															$this->_splitOnWords($text2, $nl));

				/* Get the diff in inline format. */
				$renderer = &new Text_Diff_Renderer_inline(array_merge($this->getParams(),
 																															array('split_level' => 'words')));

				/* Run the diff and get the output. */
				$ret = str_replace($nl, "<br />", $renderer->render($diff));
				//echo 'DEBUG:<pre>'.htmlspecialchars($ret).'</pre>';
				return $ret . "\n";
		}

		function _splitOnWords($string, $newlineEscape = "<br />")
		{
				$words = array();
				$length = strlen($string);
				$pos = 0;

				while ($pos < $length) {
						// Eat a word with any preceding whitespace.
						$spaces = strspn(substr($string, $pos), " \n");
						$nextpos = strcspn(substr($string, $pos + $spaces), " \n");
						$words[] = str_replace("\n", $newlineEscape, substr($string, $pos, $spaces + $nextpos));
						$pos += $spaces + $nextpos;
				}

				return $words;
		}

		function _encode(&$string)
		{
				$string = htmlspecialchars($string);
		}
		
		/**
 		* Renders a diff.
 		*
 		* @param Text_Diff $diff  A Text_Diff object.
 		*
 		* @return string  The formatted output.
 		*/
		
		function render($diff)
		{
				$xi = $yi = 1;
				$block = false;
				$context = array();

				$nlead = $this->_leading_context_lines;
				$ntrail = $this->_trailing_context_lines;

				$output = $this->_startDiff();

				$diffs = $diff->getDiff();
				foreach ($diffs as $i => $edit) {
						if (is_a($edit, 'Text_Diff_Op_copy')) {
								if (is_array($block)) {
										$keep = $i == count($diffs) - 1 ? $ntrail : $nlead + $ntrail;
										if (count($edit->orig) <= $keep) {
												$block[] = $edit;
										} else {
												if ($ntrail) {
														$context = array_slice($edit->orig, 0, $ntrail);
														$block[] = &new Text_Diff_Op_copy($context);
												}
												$bk = $this->_block($x0, $ntrail + $xi - $x0,
																						$y0, $ntrail + $yi - $y0,
																						$block);
												$output .= $bk;
												$block = false;
										}
								}
								$context = $edit->orig;
						} else {
								if (!is_array($block)) {
										$context = array_slice($context, count($context) - $nlead);
										$x0 = $xi - count($context);
										$y0 = $yi - count($context);
										$block = array();
										if ($context) {
												$block[] = &new Text_Diff_Op_copy($context);
										}
								}
								$block[] = $edit;
						}

						if ($edit->orig) {
								$xi += count($edit->orig);
						}
						if ($edit->final) {
								$yi += count($edit->final);
						}
				}

				if (is_array($block)) {
						$bk = $this->_block($x0, $xi - $x0,
																$y0, $yi - $y0,
																$block);
						$output .= $bk;
				}

				$final = $output . $this->_endDiff();
				if ($final == '') $final = '<tr><td class="diff-block">No differences.</td></tr>';
				//$final = preg_replace('#('.preg_quote($this->_ins_suffix).'|'.preg_quote($this->_del_suffix).')(.+?)('.preg_quote($this->_ins_prefix).'|'.preg_quote($this->_ins_suffix).')#', '\\1<tr><td></td><td class="diff-context>\\2</td></tr>\\3', $final);
				return '<table class="diff">'.$final.'</table>'."\n\n";
		}
		
		function _block($xbeg, $xlen, $ybeg, $ylen, &$edits)
		{
				$output = $this->_startBlock($this->_blockHeader($xbeg, $xlen, $ybeg, $ylen));

				foreach ($edits as $edit) {
						switch (strtolower(get_class($edit))) {
						case 'text_diff_op_copy':
								$output .= $this->_context($edit->orig);
								break;

						case 'text_diff_op_add':
								$output .= $this->_added($edit->final);
								break;

						case 'text_diff_op_delete':
								$output .= $this->_deleted($edit->orig);
								break;

						case 'text_diff_op_change':
								$output .= $this->_changed($edit->orig, $edit->final);
								break;
						}
				}

				return $output . $this->_endBlock();
		}

}
