<?php

namespace Nstaeger\WpPostEmailNotification;

use Nstaeger\CmsPluginFramework\Configuration;
use Nstaeger\CmsPluginFramework\Creator\Creator;
use Nstaeger\CmsPluginFramework\Plugin;
use Nstaeger\WpPostEmailNotification\Model\JobModel;
use Nstaeger\WpPostEmailNotification\Model\Option;
use Nstaeger\WpPostEmailNotification\Model\SubscriberModel;

class WpPostEmailNotificationPlugin extends Plugin
{
    function __construct(Configuration $configuration, Creator $creator)
    {
        parent::__construct($configuration, $creator);

        $this->menu()->registerAdminMenuItem('WP Post Email Notification')
             ->withAction('AdminPageController@optionsPage')
             ->withAsset('js/bundle/admin-options.js');

        // TODO access control!
        $this->ajax()->delete('job')->resolveWith('AdminJobController@delete');
        $this->ajax()->get('job')->resolveWith('AdminJobController@get');
        $this->ajax()->get('option')->resolveWith('AdminOptionController@get');
        $this->ajax()->put('option')->resolveWith('AdminOptionController@update');
        $this->ajax()->post('subscribe')->resolveWith('FrontendSubscriberController@post')->forUnauthorized(true);
        $this->ajax()->delete('subscriber')->resolveWith('AdminSubscriberController@delete');
        $this->ajax()->get('subscriber')->resolveWith('AdminSubscriberController@get');
        $this->ajax()->post('subscriber')->resolveWith('AdminSubscriberController@post');

        $this->events()->on('loaded', array($this, 'sendNotifications'));
        $this->events()->on('post-published', array($this, 'postPublished'));
        $this->events()->on('post-unpublished', array($this, 'postUnpublished'));
    }

    /**
     * @return JobModel
     */
    public function job()
    {
        return $this->make(JobModel::class);
    }

    /**
     * @return Option
     */
    public function option()
    {
        return $this->make(Option::class);
    }

    public function postPublished($id)
    {
        $this->job()->createNewJob($id);
    }

    public function postUnpublished($id)
    {
        $this->job()->removeJobsFor($id);
    }

    public function sendNotifications()
    {
        $numberOfMails = $this->option()->getNumberOfEmailsSendPerRequest();
        $jobs = $this->job()->getNextJob();

        if (empty($jobs)) {
            return;
        }

        foreach ($jobs as $job) {
            $recipients = $this->subscriber()->getEmails($job['offset'], $numberOfMails);

            if (sizeof($recipients) < $numberOfMails) {
                $this->job()->completeJob($job['id']);
            } else {
                $this->job()->rescheduleWithNewOffset($job['id'], sizeof($recipients));
            }

            if (!empty($recipients)) {
                $post = get_post($job['post_id']);

                $blogName = get_bloginfo('name');
                $postAuthorName = get_the_author_meta('display_name', $post->post_author);
                $postLink = get_permalink($post->ID);
                $postTitle = $post->post_title;

                $subject = $this->option()->getEmailSubject();
                $subject = str_replace(
                    ['@@blog.name', '@@post.author.name', '@@post.link', '@@post.title'],
                    [$blogName, $postAuthorName, $postLink, $postTitle],
                    $subject
                );

                $message = $this->option()->getEmailBody();
                $message = str_replace(
                    ['@@blog.name', '@@post.author.name', '@@post.link', '@@post.title'],
                    [$blogName, $postAuthorName, $postLink, $postTitle],
                    $message
                );

                $headers[] = '';

                foreach ($recipients as $recipient) {
                    wp_mail([$recipient['email']], $subject, $message, $headers);
                }
            }
        }
    }

    /**
     * @return SubscriberModel
     */
    public function subscriber()
    {
        return $this->make(SubscriberModel::class);
    }

    public function activate()
    {
        $this->job()->createTable();
        $this->subscriber()->createTable();
        $this->option()->createDefaults();
    }
}
