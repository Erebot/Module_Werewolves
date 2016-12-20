<?php
/*
    This file is part of Erebot.

    Erebot is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Erebot is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Erebot.  If not, see <http://www.gnu.org/licenses/>.
*/

namespace Erebot\Module\Werewolves;

class DiGraph
{
    protected $edges;

    public function __construct()
    {
        $this->clear();
    }

    public function clear()
    {
        $this->edges = array();
        return $this;
    }

    public function addNode($node)
    {
        if (!isset($this->edges[$node])) {
            $this->edges[$node] = array();
        }
        return $this;
    }

    public function addEdge($u, $v)
    {
        $this->addNode($u)->addNode($v);
        $this->edges[$u][] = $v;
        return $this;
    }

    public function getNodes()
    {
        return array_keys($this->edges);
    }

    public function getEdges()
    {
        $edges = array();
        foreach ($this->edges as $u => $succ){
            foreach ($succ as $v) {
                $edges[] = array($u, $v);
            }
        }
        return $edges;
    }

    public function sort()
    {
        $l = array();
        $g = $this->edges;
        $s = array_diff(array_keys($g), call_user_func_array('array_merge', $g));
        while (count($s)) {
            $n = array_shift($s);
            $l[] = $n;
            foreach ((array) $g[$n] as $e => $m) {
                unset($g[$n][$e]);
                $in = call_user_func_array('array_merge', $g);
                if (!in_array($m, $in)) {
                    $s[] = $m;
                }
            }
        }
        if (count(call_user_func_array('array_merge', $g))) {
            throw new \InvalidArgumentException();
        }
        return $l;
    }
}

