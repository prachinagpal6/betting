<?php

namespace Drupal\betting\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\node\Entity\Node;
use Drupal\poll\Entity\Poll;
use Drupal\poll\Entity\PollChoice;
use Drupal\user\Entity\User;

/**
 * Provides a 'Scorecard' block.
 *
 * @Block(
 *   id = "scorecard",
 *   admin_label = @Translation("Scorecard")
 * )
 */
class ScoreCard extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    // We return an empty array on purpose. The block will thus not be rendered
    // on the site. See BlockExampleTest::testBlockExampleBasic().
    $tournament = \Drupal::routeMatch()->getParameter('node');
    if ($tournament instanceof Node) {
      // You can get nid and anything else you need from the node object.
      $nid = $tournament->id();
      $matches = $tournament->get('field_matches');
      $bet_amount = $tournament->get('field_bet_amount')
        ->getValue()[0]['value'];

      $data = [];
      $users = [];
      //Aggregate all the users who participated in the poll.

      foreach ($matches as $m) {
        $mid = $m->getValue()['target_id'];
        $data[$mid] = [];
        $match = Node::load($mid);
        $data[$mid]['name'] = $match->getTitle();
        $data[$mid]['date'] = $match->get('field_date')->getString();

        $wid = $match->get('field_winner')->getValue()[0]['target_id'];
        if (is_numeric($wid)) {

          $winner = Node::load($wid)->getTitle();
          $data[$mid]['winner'] = $winner;

          $p = $match->get('field_bet')->getValue()[0]['target_id'];
          $poll = Poll::load($p);
          // $is_poll_open = $poll->isOpen();
          $choices = $poll->get('choice')->getValue();
          foreach ($choices as $c) {
            $ch = PollChoice::load($c['target_id']);
            $choice[$c['target_id']] = $ch->get('choice')
              ->getValue()[0]['value'];
          }
          $query = db_query("SELECT chid, uid FROM {poll_vote} WHERE pid = :pid", [
            ':pid' => $poll->id(),
          ]);
          $votes = $query->fetchAll();

          $data[$mid]['users'] = [];
          foreach ($votes as $vote) {
            $user = User::load($vote->uid);
            $user_vote = $choice[$vote->chid];
            $data[$mid]['users'][$user->getAccountName()]['vote'] = $user_vote;
            $user_vote_correct = ($user_vote == $winner) ? TRUE : FALSE;
            $data[$mid]['users'][$user->getAccountName()]['correct'] = $user_vote_correct;

            if (!in_array($user->getAccountName(), $users)) {
              $users[] = $user->getAccountName();
            }

          }
        }
      }
    }
    // $users = sort($users);
    $header = array_merge(['Date', 'Match'],
      $users,
      ['Winner', 'Total Amount Bet', 'Total Winners', 'Amount Won per person']);
    $rows = [];
    $user_data = array_flip($users);
    foreach ($user_data as &$udata) {
      $udata = [];
      $udata['bet'] = 0;
      $udata['won'] = 0;
      $udata['lost'] = 0;
      $udata['profit'] = 0;
      $udata['match_lost'] = 0;
      $udata['match_won'] = 0;
    }

    foreach ($data as $mat) {
      $total_bet_amount = 0;
      $total_winners = 0;
      $row = [];
      $row[] = $mat['date'];
      $row[] = $mat['name'];
      $winners = [];
      $participents = [];
      foreach ($users as $u) {
        if (isset($mat['users'][$u])) {
          $participents[] = $u;
          $row[] = $mat['users'][$u]['vote'];
          $total_bet_amount = $total_bet_amount + $bet_amount;
          if ($mat['users'][$u]['correct'] === TRUE) {
            $total_winners++;
            $winners[] = $u;

            $user_data[$u]['bet'] += $bet_amount;
            $user_data[$u]['match_won'] += 1;
          }
          else {
            $user_data[$u]['bet'] += $bet_amount;
            $user_data[$u]['lost'] += $bet_amount;
            $user_data[$u]['match_lost'] += 1;
            $user_data[$u]['profit'] = $user_data[$u]['profit'] - $bet_amount;
          }
        }
        else {
          $row[] = '-';
        }
      }
      $winning_amount = $total_bet_amount / $total_winners;
      $row[] = $mat['winner'];
      $row[] = $total_bet_amount;
      $row[] = $total_winners;
      $row[] = $winning_amount;
      $rows[] = $row;
      unset($row);

      //More calculations for later part.
      foreach ($winners as $wn) {
        $user_data[$wn]['won'] += $total_bet_amount / $total_winners;
        $user_data[$wn]['profit'] +=  $winning_amount;
      }
    }

    // Bet
    $additional_rows = [];
    foreach ([
      'Bet',
      'Won',
      'Lost',
      'Profit',
      'Match Won',
      'Match Lost',
    ] as $i => $row_val) {
      $additional_rows[$i][] = "-";
      $additional_rows[$i][] = $row_val;
      foreach ($user_data as $ud) {
        $additional_rows[$i][] = $ud[strtolower(str_replace(' ', '_', $row_val))];
      }
      $additional_rows[$i][] = "-";
      $additional_rows[$i][] = "-";
      $additional_rows[$i][] = "-";
      $additional_rows[$i][] = "-";
    }

    $rows = array_merge($additional_rows, $rows);
    return
      [
        '#theme' => 'table',
        '#rows' => $rows,
        '#header' => $header,
      ];

  }

}
