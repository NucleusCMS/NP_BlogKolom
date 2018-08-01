<?php
/**
  * This plugin can be used to insert your blog items or blog items title within a table
  * History:
  *	  v0.23:
  *	  v0.2:
  *	  v0.1: initial plugin
  */

class NP_BlogKolom extends NucleusPlugin {

	var $now = "odd";

	function getName()              { return 'Show in table'; }
	function getAuthor()            { return '-=Xiffy=-, yama.kyms'; }
	function getURL()               { return 'http://japan.nucleuscms.org/wiki/plugins:blogkolom'; }
	function getVersion()           { return '0.24'; }
	function supportsFeature($what) { return ($what=='SqlTablePrefix')?1:0; }
	function getDescription()       {
	return 'Call this instead of &lt;%blog()%&gt; to show your items side by side in a table by using &lt;%BlogKolom(template,2x5)%&gt; or &lt;%BlogKolom(template,2x5(1))%&gt;.';
                                    }

	function doTemplateVar(	&$item)
	{
		global $startpos;
        $params = func_get_args();
        $type = $params[1];
        $p1 = isset($params[2]) ? $params[2] : '';
		$pos = isset($startpos) ? (int)$startpos : 0 ;
		$amount = $this->amount;
		if ($amount < $this->getTotal())
		{
			switch($type=strtolower($type))
			{
			case 'prev':
				if ($amount <= $pos)
				{
					$str  = '<a href="' . hsc($this->url($pos-$amount)) . '">';
					$str .= hsc($p1) . '</a>';
					echo $str;
				}
				return;
			case 'next':
				if ( $pos+$amount < $this->getTotal() )
				{
					$str  = '<a href="' . hsc($this->url($pos+$amount)) . '">';
					$str .= hsc($p1) . '</a>';
					echo $str;
				}
				return;
			case '|':
				if ( ($amount <= $pos) and ( $pos+$amount < $this->getTotal()) )
				{
					echo " | ";
				}
				return;
			default: break;
			}
		}
	}

	function doSkinVar($skinType)
	{
		global $manager, $blog, $CONF, $startpos, $catid;

		$p = func_get_args();
		$template = $p[1];
		$amount   = isset($p[2]) ? $p[2] : '2x5';
		$category = isset($p[3]) ? $p[3] : '';
		
		if($blog && !empty($catid)) $category = $blog->getCategoryName($catid);
//		if($category == '' || !empty($catid)) $category = $blog->getCategoryName($catid);
		$amount = trim($amount);
		$offset = preg_match('@\(([0-9]+)\)$@', $amount);
		if (empty($offset))   { $offset   = 0 ; }
		if (empty($startpos)) { $startpos = 0 ; }
		$cols = preg_split("/[^0-9]+/", $amount);
		$amount = intval($cols[0] * $cols[1]);
		if ($cols[0]>20)  { $cols[0]= 20;  }
		if ($cols[1]>100) { $cols[1]=100;  }
		$this->amount = $amount;
		$this->readLogAmount($template,
		                     $amount,
		                     '',
		                     '',
		                     1,
		                     1,
		                     $category,
		                     $offset,
		                     $startpos,
		                     $cols[0]
		                     );
	}

	function readLogAmount($template,
	                       $amountEntries,
	                       $extraQuery,
	                       $highlight,
	                       $comments,
	                       $dateheads,
	                       $category,
	                       $offset = 0,
	                       $startpos = 0,
	                       $cols
	                       )
	{
		global $blogName,$manager, $blog, $CONF, $HTTP_COOKIE_VARS;
        if ($blogName)
        {
			$b =& $manager->getBlog(getBlogIDFromName($params[2]));
		}
		elseif ($blog)
		{
			$b =& $blog;
		}
		else
		{
			$b =& $manager->getBlog($CONF['DefaultBlog']);
		}
		$this->_setBlogCategory($b, $category);
		$query = $b->getSqlBlog($extraQuery);

		if ($amountEntries > 0)
		{
			// $offset zou moeten worden:
			// (($startpos / $amountentries) + 1) * $offset ... later testen ...
			$query .= ' LIMIT ' . intval($startpos + $offset) . ',' . intval($amountEntries);
		}
		return $this->showUsingQuery($template, $query, $highlight, $comments, $dateheads, $cols);
	}
	
	function showUsingQuery($templateName,
	                        $query,
	                        $highlight = '',
	                        $comments = 0,
	                        $dateheads = 1,
	                        $cols
	                        )
	{
		global $blogName, $manager, $blog, $CONF, $HTTP_COOKIE_VARS, $currentTemplateName;
		if ($blogName)
		{
			$b =& $manager->getBlog(getBlogIDFromName($params[2]));
		}
		elseif ($blog)
		{
			$b =& $blog;
		}
		else
		{
			$b =& $manager->getBlog($CONF['DefaultBlog']);
		}

		$lastVisit = cookieVar($CONF['CookiePrefix'] .'lastVisit');
		if ($lastVisit != 0) { $lastVisit = $b->getCorrectTime($lastVisit); }
		$currentTemplateName = $templateName;
		$template = & $manager->getTemplate($templateName);
		
		// create parser object & action handler
		$actions = new ITEMACTIONS($b);
		$parser  = new PARSER($actions->getDefinedActions(),$actions);
		$actions->setTemplate($template);
		$actions->setHighlight($highlight);
		$actions->setLastVisit($lastVisit);
		$actions->setParser($parser);
		$actions->setShowComments($comments);
		
		// execute query
		$items = sql_query($query);
	   
		// loop over all items
		$old_date = 0;

		$rowCount = intval(0);
		echo "<table class=\"blogkolom\">";

		$parser->parse($template['ITEM_HEADER']);
		$param = array
        (
            'blog' => &$b,
            'item' => &$item
        );
		$manager->notify
		(
			'PreItem',$param
		);
		
		// loop over all items
		$itemcount = 0;
		while ($item = sql_fetch_object($items))
		{
			$item->timestamp = strtotime($item->itime);	// string timestamp -> unix timestamp

			// action handler needs to know the item we're handling
			$actions->setCurrentItem($item);

			if (($rowCount % $cols) == 0 )
			{
				if ($rowCount >= $cols)
				{
					echo "</tr>\n";
				}
				$remaincells = $cols;
				echo "<tr class=\"$this->now\">";
				if ($this->now == "odd")
				{
					$this->now ="even";
				}
				else
				{
					$this->now = "odd";
				}
			}
			$rowCount = $rowCount + 1;
			$itemcount = $itemcount +1;
			$remaincells--;
			echo "<td>";
		
			// add date header if needed
			if ($dateheads)
			{
				$new_date = date('dFY',$item->timestamp);
				if ($new_date != $old_date)
				{
					// unless this is the first time, write date footer
					$timestamp = $item->timestamp;
					if ($old_date != 0)
					{
						$oldTS = strtotime($old_date);
						$param = array
                        (
                            'blog'		=> &$b,
                            'timestamp'	=> $oldTS
                        );
						$manager->notify
						(
							'PreDateFoot',$param
						);
						if(isset($template['DATE_FOOTER'])) {
							$tmp_footer = strftime($template['DATE_FOOTER'], $oldTS);
							$parser->parse($tmp_footer);
						}
                        $param = array
                        (
                            'blog'		=> &$b,
                            'timestamp' => $oldTS
                        );
						$manager->notify
						(
							'PostDateFoot',$param
						);						
					}
                    $param = array
                    (
                        'blog'		=> &$b,
                        'timestamp' => $timestamp
                    );
					$manager->notify
					(
						'PreDateHead',$param
					);
					// note, to use templatvars in the dateheader, the %-characters need to be doubled in
					// order to be preserved by strftime
					$tmp_header = strftime((isset($template['DATE_HEADER']) ? $template['DATE_HEADER'] : null), $timestamp);
					$parser->parse($tmp_header);
                    $param = array
                    (
                        'blog'		=> &$b,
                        'timestamp'	=> $timestamp
                    );
					$manager->notify
					(
						'PostDateHead',$param
					);
				}
				$old_date = $new_date;
			}
			
			// parse item
			$numrows = sql_num_rows($items);
			$parser->parse($template['ITEM']);			
			if ($itemcount == $numrows)
			{
                $param = array
                (
                    'blog'	=> &$b,
                    'item'	=> &$item
                );
				$manager->notify
				(
					'PostItem',$param
				);
			}
			// and close this tag
			echo '</td>';
		}
		while ($remaincells)
		{
			echo '<td class="blank">&nbsp;</td>';
			$remaincells--;
		}
		
		// add another date footer if there was at least one item
		if (($numrows > 0) && $dateheads)
		{
            $param = array
            (
                'blog'		=> &$b,
                'timestamp'	=> strtotime($old_date)
            );
			$manager->notify
			(
				'PreDateFoot',$param
			);
			$parser->parse($template['DATE_FOOTER']);
            $param = array
            (
                'blog'		=> &$b,
                'timestamp'	=> strtotime($old_date)
            );
			$manager->notify
			(
				'PostDateFoot',$param
			);
		}

		echo '</tr></table>';		
		$parser->parse($template['ITEM_FOOTER']);
		sql_free_result($items);	// free memory
		return $numrows;
	}

	function getTotal()
	{
		global $blog, $query, $amount, $catid, $subcatid;
		if (isset($this->total)) { return $this->total; }
		$scid=isset($subcatid)?(int)$subcatid:0;
		if ($query)
		{
			$highlight='';
			$sqlquery = $blog->getSqlSearch($query, $amount, $highlight,'count');
			$this->total=(int)quickQuery($sqlquery);
		}
		elseif ( $scid && $this->getOption('multicat')=='yes')
		{
			$sqlquery = $blog->getSqlBlog('AND i.inumber=p.item_id AND p.subcategories REGEXP "(^|,)'.(int)$scid.'(,|$)" ','count');
			$sqlquery = preg_replace('/^([\s]*)SELECT[\s]([^\'"=]*)[\s]FROM[\s]/i',
				'SELECT COUNT(*) as result FROM '.sql_table('plug_multiple_categories').' as p, ',$sqlquery);
			$this->total=(int)quickQuery($sqlquery);
		}
		elseif ($catid && $this->getOption('multicat')=='yes')
		{
			$sqlquery = $blog->getSqlBlog('AND i.inumber=p.item_id AND p.categories REGEXP "(^|,)'.(int)$catid.'(,|$)" '
				.'AND NOT i.icat='.(int)$catid.' ','count');
			$sqlquery = preg_replace('/^([\s]*)SELECT[\s]([^\'"=]*)[\s]FROM[\s]/i',
				'SELECT COUNT(*) as result FROM '.sql_table('plug_multiple_categories').' as p, ',$sqlquery);
			$this->total=(int)quickQuery($sqlquery);
			$sqlquery = $blog->getSqlBlog('AND i.icat='.(int)$catid.' ','count');
			$sqlquery = preg_replace('/^([\s]*)SELECT[\s]([^\'"=]*)[\s]FROM[\s]/i','SELECT COUNT(*) as result FROM ',$sqlquery);
			$this->total+=(int)quickQuery($sqlquery);
		}
		else
		{
			$case=$catid?'AND i.icat='.(int)$catid.' ':'';
			$sqlquery = $blog->getSqlBlog($case,'count');
			$this->total=(int)quickQuery($sqlquery);
		}
		return $this->total;
	}

	function url($pos)
	{
		$qs_array = $_GET;
		$qs_array['startpos'] = $pos;
		unset($qs_array['virtualpath']);
		unset($qs_array['page']);
		$qs = '';
		foreach($qs_array as $key=>$value)
		{
			$qs .= '&' . hsc($key) . '=' . hsc($value);
		}
		$qs = ltrim($qs, '&');
		return '?'. $qs;
	}

	function _setBlogCategory(&$blog, $catname)
	{
		global $catid;
		if ($catname != '')
			$blog->setSelectedCategoryByName($catname);
		else
			$blog->setSelectedCategory($catid);
	}
}
