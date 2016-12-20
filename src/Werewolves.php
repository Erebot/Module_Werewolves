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

namespace Erebot\Module;

class Werewolves
{
    protected $chans;
    protected $creator;

    const WEREWOLVES_RATIO  = 0.35;
    const DELAY_JOIN = 120;

    public function reload($flags)
    {
        if ($flags & self::RELOAD_MEMBERS) {
            $this->chans = array();
        }

        if ($flags & self::RELOAD_HANDLERS) {
            $registry   = $this->connection->getModule('\\Erebot\\Module\\TriggerRegistry');
            if (!($flags & self::RELOAD_INIT)) {
                $this->connection->removeEventHandler(
                    $this->creator['handler']
                );
                $registry->freeTriggers($this->creator['trigger'], $registry::MATCH_ANY);
            }

            $triggerCreate = $this->parseString('trigger_create', 'werewolves');
            $this->creator['trigger']  = $registry->registerTriggers($triggerCreate, $registry::MATCH_ANY);
            if ($this->creator['trigger'] === null) {
                $fmt = $this->getFormatter(false);
                throw new \Exception(
                    $fmt->_(
                        'Could not register Werewolves creation trigger'
                    )
                );
            }

            $this->creator['handler']  = new \Erebot\EventHandler(
                \Erebot\CallableWrapper::wrap(array($this, 'handleCreate')),
                new \Erebot\Event\Match\All(
                    new \Erebot\Event\Match\Type(
                        '\\Erebot\\Interfaces\\Event\\ChanText'
                    ),
                    new \Erebot\Event\Match\Any(
                        new \Erebot\Event\Match\TextStatic($triggerCreate, true),
                        new \Erebot\Event\Match\TextWildcard(
                            $triggerCreate.' *',
                            true
                        )
                    )
                )
            );
            $this->connection->addEventHandler($this->creator['handler']);
        }
    }

    public function getLogo()
    {
        return  \Erebot\StylingInterface::CODE_BOLD .
                \Erebot\StylingInterface::CODE_COLOR.'4,1 ' .
                "The Werewolves of Millers Hollow" .
                \Erebot\StylingInterface::CODE_COLOR .
                \Erebot\StylingInterface::CODE_BOLD;
    }

    public function handleCreate(
        \Erebot\Interfaces\EventHandler   $handler,
        \Erebot\Interfaces\Event\ChanText $event
    ) {
        $nick       =   $event->getSource();
        $chan       =   $event->getChan();
        $rules      =   strtolower($event->getText()->getTokens(1));
        $fmt        =   $this->getFormatter($chan);

        if (isset($this->chans[$chan])) {
            $infos      =&  $this->chans[$chan];
            $creator    =   $infos['game']->getCreator();
            $msg        =   $fmt->_(
                'A game of <var name="logo"/> is already running, '.
                'managed by <var name="creator"/>. '.
                'Say "<b><var name="trigger"/></b>" to join it.',
                array(
                    'logo'      => $this->getLogo(),
                    'creator'   => (string) $creator,
                    'trigger'   => $infos['triggers']['join'],
                )
            );
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(true);
        }

        $registry   =   $this->connection->getModule('\\Erebot\\Module\\TriggerRegistry');
        $triggers   =   array(
            'join'  => $this->parseString('trigger_join', 'jo'),
            'peek'  => $this->parseString('trigger_peek', 'peek'),
            'vote'  => $this->parseString('trigger_vote', 'vote'),
        );
        $token  = $registry->registerTriggers($triggers, $chan);
        if ($token === null) {
            $msg = $fmt->_(
                'Unable to register triggers for <var name="logo"/>',
                array('logo' => $this->getLogo())
            );
            $this->sendMessage($chan, $msg);
            return $event->preventDefault(true);
        }

        $this->chans[$chan] = array();
        $infos  =&  $this->chans[$chan];

        $tracker = $this->connection->getModule('\\Erebot\\Module\\IrcTracker');
        $creator                    =   $tracker->startTracking($nick);
        $infos['triggers_token']    =   $token;
        $infos['triggers']          =&  $triggers;
        $infos['game']              =   new \Erebot\Module\Werewolves\Game($creator);

        $infos['handlers']['vote'] = new \Erebot\EventHandler(
            \Erebot\CallableWrapper::wrap(array($this, 'handleVote')),
            new \Erebot\Event\Match\All(
                new \Erebot\Event\Match\Type(
                    '\\Erebot\\Interfaces\\Event\\ChanText'
                ),
                new \Erebot\Event\Match\TextWildcard(
                    $triggers['vote'].' *',
                    null
                ),
                new \Erebot\Event\Match\Chan($chan)
            )
        );

        $infos['handlers']['peek']          = new \Erebot\EventHandler(
            \Erebot\CallableWrapper::wrap(array($this, 'handlePeek')),
            new \Erebot\Event\Match\All(
                new \Erebot\Event\Match\Type(
                    '\\Erebot\\Interfaces\\Event\\ChanText'
                ),
                new \Erebot\Event\Match\TextWildcard(
                    $triggers['peek'].' *',
                    null
                ),
                new \Erebot\Event\Match\Chan($chan)
            )
        );

        $infos['handlers']['join']          = new \Erebot\EventHandler(
            \Erebot\CallableWrapper::wrap(array($this, 'handleJoin')),
            new \Erebot\Event\Match\All(
                new \Erebot\Event\Match\Type(
                    '\\Erebot\\Interfaces\\Event\\ChanText'
                ),
                new \Erebot\Event\Match\TextStatic($triggers['join'], null),
                new \Erebot\Event\Match\Chan($chan)
            )
        );

        foreach ($infos['handlers'] as $handler) {
            $this->connection->addEventHandler($handler);
        }

        $msg = $fmt->_(
            'A new game of <var name="logo"/> has been created in <var name="chan"/>. '.
            'You have <var name="delay"> seconds left to register for the game ' .
            'by saying "<b><var name="trigger"/></b>".',
            array(
                'logo'      => $this->getLogo(),
                'chan'      => $chan,
                'trigger'   => $infos['triggers']['join'],
                'delay'     => self::DELAY_JOIN,
            )
        );
        $this->sendMessage($chan, $msg);
        return $event->preventDefault(true);
    }

    public function handleJoin()
    {
        $nick   = $event->getSource();
        $chan   = $event->getChan();
        $fmt    = $this->getFormatter($chan);

        if (!isset($this->chans[$chan])) {
            return;
        }
        $game =& $this->chans[$chan]['game'];

        $players =& $game->getPlayers();
        foreach ($players as &$player) {
            if (!strcasecmp((string) $player->getPlayer(), $nick)) {
                $msg = $fmt->_(
                    '<var name="logo"/> You\'re already '.
                    'in the game <b><var name="nick"/></b>!',
                    array(
                        'logo'  => $this->getLogo(),
                        'nick'  => $nick,
                    )
                );
                $this->sendMessage($chan, $msg);
                return $event->preventDefault(true);
            }
        }

        $msg = $fmt->_(
            '<b><var name="nick"/></b> joins this '.
            '<var name="logo"/> game.',
            array(
                'nick'  => $nick,
                'logo'  => $this->getLogo(),
            )
        );
        $this->sendMessage($chan, $msg);

        $tracker = $this->connection->getModule('\\Erebot\\Module\\IrcTracker');
        $token  =   $tracker->startTracking($nick);
    }
}
