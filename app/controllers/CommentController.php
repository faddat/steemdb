<?php
namespace SteemDB\Controllers;

use MongoDB\BSON\UTCDateTime;
use SteemDB\Models\Comment;
use SteemDB\Models\Account;

class CommentController extends ControllerBase
{

  public function listAction()
  {
    $query = array(
      'depth' => 0,
    );
    $sort = array(
      'created' => -1,
    );
    $limit = 50;
    $this->view->comments = Comment::find(array(
      $query,
      "sort" => $sort,
      "limit" => $limit
    ));
  }

  public function viewAction()
  {
    // Get parameters
    $tag = $this->dispatcher->getParam("tag", "string");
    $author = $this->dispatcher->getParam("author", "string");
    $permlink = $this->dispatcher->getParam("permlink", "string");
    // Load the Post
    $query = array(
      '_id' => $author . '/' . $permlink
    );
    $comment = $this->view->comment = Comment::findFirst(array(
      $query
    ));
    if(!$comment) {
      $this->flashSession->error('The specified post does not exist on SteemDB currently.');
      $this->response->redirect();
      return;
    }
    // Sort the votes by rshares
    $votes = $comment->active_votes;
    usort($votes, function($a, $b) {
      return $b->rshares - $a->rshares;
    });
    $this->view->votes = $votes;
    // And get it's replies
    $query = array(
      'parent_permlink' => $permlink
    );
    $this->view->replies = Comment::find(array(
      $query,
      "sort" => ['created' => -1]
    ));
    // And finally the author
    $query = array(
      'name' => $comment->author
    );
    $this->view->author = Account::findFirst(array($query));
    // And some additional posts
    $this->view->posts = Comment::find(array(
      array(
        'author' => $comment->author,
        'depth' => 0,
      ),
      'sort' => array('created' => -1),
      'limit' => 5
    ));
  }

  public function dailyAction() {
    $sortFields = [
      "combined_payout" => -1,
    ];
    $this->view->sort = $sort = $this->dispatcher->getParam("sort");
    switch($sort) {
      case "votes":
        $sortFields = ["net_votes" => -1];
        break;
    }
    $this->view->date = $date = strtotime($this->dispatcher->getParam("date") ?: date("Y-m-d"));
    $this->view->tag = $tag = $this->dispatcher->getParam("tag", "string") ?: "all";
    $query = [
      'depth' => 0,
      'created' => [
        '$gte' => new UTCDateTime($date * 1000),
        '$lte' => new UTCDateTime(($date + 86400) * 1000),
      ],
    ];
    if($tag !== 'all') $query['category'] = $tag;
    $this->view->comments = Comment::aggregate([
      ['$match' => $query],
      ['$project' => [
        '_id' => '$_id',
        'created' => '$created',
        'url' => '$url',
        'title' => '$title',
        'author' => '$author',
        'author_reputation' => '$author_reputation',
        'category' => '$category',
        'net_votes' => '$net_votes',
        'combined_payout' => ['$add' => ['$total_payout_value', '$total_pending_payout_value']],
        'total_payout_value' => '$total_payout_value',
        'total_pending_payout_value' => '$total_pending_payout_value'
      ]],
      ['$sort' => $sortFields],
      ['$limit' => 10]
    ]);
  }

}
