<?php

    namespace thebuggenie\modules\publish\entities;

    use TBGContext,
        TBGSettings,
        TBGTextParser,
        TBGTextParserMarkdown,
        TBGProject,
        TBGUser,
        TBGFile,
        TBGEvent,
        thebuggenie\modules\publish\entities\b2db\UserArticles,
        thebuggenie\modules\publish\entities\b2db\Articles,
        thebuggenie\modules\publish\entities\b2db\ArticleCategories,
        thebuggenie\modules\publish\entities\b2db\ArticleFiles,
        thebuggenie\modules\publish\entities\b2db\ArticleHistory,
        thebuggenie\modules\publish\entities\b2db\ArticleLinks;

    /**
     * @Table(name="\thebuggenie\modules\publish\entities\b2db\Articles")
     */
    class Article extends \TBGIdentifiableScopedClass
    {

        const TYPE_WIKI = 1;
        const TYPE_MANUAL = 2;

        /**
         * The article author
         *
         * @var \TBGUser
         * @Column(type="integer", length=10)
         * @Relates(class="\TBGUser")
         */
        protected $_author = null;

        /**
         * @Column(type="string", length=200)
         */
        protected $_name;

        /**
         * @Column(type="string", length=200)
         */
        protected $_manual_name;

        /**
         * When the article was posted
         *
         * @var integer
         * @Column(type="integer", length=10)
         */
        protected $_date = null;

        /**
         * What type of article this is
         *
         * @var integer
         * @Column(type="integer", length=10, default=1)
         */
        protected $_article_type = self::TYPE_WIKI;

        /**
         * The old article content, used for history when saving
         *
         * @var string
         */
        protected $_old_content = null;

        /**
         * The article content
         *
         * @var string
         * @Column(type="text")
         */
        protected $_content = null;

        /**
         * The article content syntax
         *
         * @var integer
         * @Column(type="integer", length=3, default=1)
         */
        protected $_content_syntax = TBGSettings::SYNTAX_MW;

        /**
         * Whether the article is published or not
         *
         * @var boolean
         * @Column(type="boolean")
         */
        protected $_is_published = false;

        /**
         * The parent article, if this article has one
         *
         * @var \thebuggenie\modules\publish\entities\Article
         * @Column(type="integer", length=10)
         * @Relates(class="\thebuggenie\modules\publish\entities\Article")
         */
        protected $_parent_article_id = false;

        /**
         * Child article, if this article has any
         *
         * @var array|\thebuggenie\modules\publish\entities\Article
         * @Relates(class="\thebuggenie\modules\publish\entities\Article", collection=true, foreign_column="parent_article_id")
         */
        protected $_child_articles = null;

        /**
         * A list of articles that links to this article
         *
         * @var array
         */
        protected $_linking_articles = null;

        /**
         * A list of categories this article is in
         *
         * @var array
         */
        protected $_categories = null;

        /**
         * Array of files attached to this article
         *
         * @var array
         */
        protected $_files = null;

        /**
         * A list of subcategories for this category
         *
         * @var array
         */
        protected $_subcategories = null;

        /**
         * A list of page in this category
         *
         * @var array
         */
        protected $_category_articles = null;

        /**
         * Whether or not this page is a category page
         *
         * @var boolean
         */
        protected $_is_category = null;
        protected $_history = null;
        protected $_category_name = null;
        protected $_namespaces = null;
        protected $_redirect_article = null;

        /**
         * Array of users that are subscribed to this issue
         *
         * @var array
         * @Relates(class="\TBGUser", collection=true, manytomany=true, joinclass="\thebuggenie\modules\publish\entities\b2db\UserArticles")
         */
        protected $_subscribers = null;

        /**
         * Article constructor
         *
         * @param \b2db\Row $row
         */
        public function _construct(\b2db\Row $row, $foreign_key = null)
        {
            $this->_content = str_replace("\r\n", "\n", $this->_content);
            $this->_old_content = $this->_content;
        }

        protected function _preSave($is_new)
        {
            parent::_preSave($is_new);
            $this->_date = NOW;
            $this->_author = TBGContext::getUser();
        }

        protected function _postDelete()
        {
            ArticleLinks::getTable()->deleteLinksByArticle($this->getName());
            ArticleCategories::getTable()->deleteCategoriesByArticle($this->getName());
            ArticleHistory::getTable()->deleteHistoryByArticle($this->getName());
            ArticleFiles::getTable()->deleteFilesByArticleID($this->getID());
        }

        public static function findArticlesByContentAndProject($content, $project, $limit = 5, $offset = 0)
        {
            $articles = array();
            list ($resultcount, $res) = Articles::getTable()->findArticlesContaining($content, $project, $limit, $offset);

            if ($res)
            {
                while ($row = $res->getNextRow())
                {
                    $article = self::getByName($row->get(Articles::NAME), $row);
                    if ($article->hasAccess())
                    {
                        $articles[$row->get(Articles::ID)] = $article;
                    }
                    else
                    {
                        $resultcount--;
                    }
                }
            }

            return array($resultcount, $articles);
        }

        public static function getByName($article_name, $row = null)
        {
            return Articles::getTable()->getArticleByName($article_name);
        }

        public static function doesArticleExist($article_name)
        {
            return Articles::getTable()->doesArticleExist($article_name);
        }

        public static function deleteByName($article_name)
        {
            Articles::getTable()->deleteArticleByName($article_name);
            ArticleLinks::getTable()->deleteLinksByArticle($article_name);
        }

        public static function createNew($name, $content, $published, $scope = null, $options = array())
        {
            $user_id = (TBGContext::getUser() instanceof TBGUser) ? TBGContext::getUser()->getID() : 0;

            $article = new Article();
            $article->setName($name);
            $article->setContent($content);

            if (!isset($options['noauthor']))
                $article->setAuthor($user_id);
            else
                $article->setAuthor(0);

            if ($scope !== null)
                $article->setScope($scope);

            $article->doSave($options);

            return $article->getID();
        }

        public function __toString()
        {
            return $this->_content;
        }

        public function hasContent()
        {
            return ($this->_content != '') ? true : false;
        }

        public function getContent()
        {
            return $this->_content;
        }

        public function getParsedContent($options = array())
        {
            switch ($this->_content_syntax)
            {
                case TBGSettings::SYNTAX_MD:
                    $parser = new TBGTextParserMarkdown();
                    $text = $parser->transform($this->_content);
                    break;
                case TBGSettings::SYNTAX_PT:
                    $options = array('plain' => true);
                case TBGSettings::SYNTAX_MW:
                default:
                    $wiki_parser = new TBGTextParser($this->_content, true, $this->getID());
                    foreach ($options as $option => $value)
                    {
                        $wiki_parser->setOption($option, $value);
                    }
                    $text = $wiki_parser->getParsedText();
                    break;
            }

            return $text;
        }

        public function setContentSyntax($syntax)
        {
            if (!is_numeric($syntax))
                $syntax = TBGSettings::getSyntaxValue($syntax);

            $this->_content_syntax = $syntax;
        }

        public function getContentSyntax()
        {
            return $this->_content_syntax;
        }

        public function setContent($content)
        {
            $this->_content = str_replace("\r\n", "\n", $content);
            if ($this->_content_syntax == TBGSettings::SYNTAX_MW)
            {
                $parser = new TBGTextParser($content);
                $parser->doParse();
                $this->_populateCategories($parser->getCategories());
            }
        }

        public function getTitle()
        {
            return $this->getName();
        }

        public function getLastUpdatedDate()
        {
            return $this->getPostedDate();
        }

        protected function _populateLinkingArticles()
        {
            if ($this->_linking_articles === null)
            {
                $this->_linking_articles = Articles::getTable()->getAllByLinksToArticleName($this->_name);
                foreach ($this->_linking_articles as $k => $article)
                    if (!$article->hasAccess())
                        unset($this->_linking_articles[$k]);
            }
        }

        public function getLinkingArticles()
        {
            $this->_populateLinkingArticles();
            return $this->_linking_articles;
        }

        protected function _populateSubCategories()
        {
            if ($this->_subcategories === null)
            {
                $this->_subcategories = array();
                if ($res = ArticleCategories::getTable()->getSubCategories($this->getCategoryName()))
                {
                    while ($row = $res->getNextRow())
                    {
                        try
                        {
                            $this->_subcategories[$row->get(ArticleCategories::ARTICLE_NAME)] = Articles::getTable()->getArticleByName($row->get(ArticleCategories::ARTICLE_NAME));
                        }
                        catch (Exception $e)
                        {

                        }
                    }
                }
            }
        }

        public function getSubCategories()
        {
            $this->_populateSubCategories();
            return $this->_subcategories;
        }

        protected function _populateCategoryArticles()
        {
            if ($this->_category_articles === null)
            {
                $this->_category_articles = array();
                if ($res = ArticleCategories::getTable()->getCategoryArticles($this->getCategoryName()))
                {
                    while ($row = $res->getNextRow())
                    {
                        try
                        {
                            $this->_category_articles[$row->get(ArticleCategories::ARTICLE_NAME)] = Articles::getTable()->getArticleByName($row->get(ArticleCategories::ARTICLE_NAME));
                        }
                        catch (Exception $e)
                        {

                        }
                    }
                }
            }
        }

        public function getCategoryArticles()
        {
            $this->_populateCategoryArticles();
            return $this->_category_articles;
        }

        protected function _populateCategories($categories = null)
        {
            if ($this->_categories === null || $categories !== null)
            {
                $this->_categories = array();
                if ($categories === null)
                {
                    if ($res = ArticleCategories::getTable()->getArticleCategories($this->getName()))
                    {
                        while ($row = $res->getNextRow())
                        {
                            $this->_categories[] = $row->get(ArticleCategories::CATEGORY_NAME);
                        }
                    }
                }
                else
                {
                    foreach ($categories as $category => $occurrences)
                    {
                        $this->_categories[] = $category;
                    }
                }
            }
        }

        public function getCategories()
        {
            $this->_populateCategories();
            return $this->_categories;
        }

        protected function _retrieveLinksAndCategoriesFromContent($options = array())
        {
            $parser = new TBGTextParser(html_entity_decode($this->_content));
            $options['no_code_highlighting'] = true;
            $parser->doParse($options);
            return array($parser->getInternalLinks(), $parser->getCategories());
        }

        public function isCategory()
        {
            if ($this->_is_category === null)
            {
                $names = explode(':', $this->_name);
                if (count($names) > 0)
                {
                    $this->_is_category = (bool) ($names[0] == 'Category');
                }
                else
                {
                    $this->_is_category = false;
                }
            }
            return $this->_is_category;
        }

        public function getSpacedName()
        {
            return preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $this->getName());
        }

        public function getCategoryName()
        {
            if ($this->_category_name === null)
            {
                $this->_category_name = mb_substr($this->_name, mb_strpos($this->_name, ':') + 1);
            }
            return $this->_category_name;
        }

        protected function _populateHistory()
        {
            if ($this->_history === null)
            {
                $this->_history = array();
                $history = ArticleHistory::getTable()->getHistoryByArticleName($this->getName());

                if ($history)
                {
                    while ($row = $history->getNextRow())
                    {
                        $author = ($row->get(ArticleHistory::AUTHOR)) ? TBGContext::factory()->TBGUser($row->get(ArticleHistory::AUTHOR)) : null;
                        $this->_history[$row->get(ArticleHistory::REVISION)] = array('old_content' => $row->get(ArticleHistory::OLD_CONTENT), 'new_content' => $row->get(ArticleHistory::NEW_CONTENT), 'change_reason' => $row->get(ArticleHistory::REASON), 'updated' => $row->get(ArticleHistory::DATE), 'author' => $author);
                    }
                }
            }
        }

        public function getHistory()
        {
            $this->_populateHistory();
            return $this->_history;
        }

        public function isRedirect()
        {
            if (mb_substr($this->getContent(), 0, 10) == "#REDIRECT ")
            {
                $content = explode("\n", $this->getContent());
                preg_match('/(\[\[([^\]]*?)\]\])$/im', mb_substr(array_shift($content), 10), $matches);
                if (count($matches) == 3)
                {
                    $this->_redirect_article = $matches[2];
                    return true;
                }
            }

            return false;
        }

        public function getRedirectArticle()
        {
            if (!$this->isRedirect())
                return null;

            if (!$this->_redirect_article instanceof Article)
            {
                $article = Articles::getTable()->getArticleByName($this->_redirect_article);
                if ($article instanceof Article)
                    $this->_redirect_article = $article;
            }

            return $this->_redirect_article;
        }

        public function getRedirectArticleName()
        {
            return ($this->_redirect_article instanceof Article) ? $this->_redirect_article->getName() : $this->_redirect_article;
        }

        public function doSave($options = array(), $reason = null)
        {
            if (Articles::getTable()->doesNameConflictExist($this->_name, $this->_id, TBGContext::getScope()->getID()))
            {
                if (!array_key_exists('overwrite', $options) || !$options['overwrite'])
                {
                    throw new \Exception(TBGContext::getI18n()->__('Another article with this name already exists'));
                }
            }
            $user_id = (TBGContext::getUser() instanceof TBGUser) ? TBGContext::getUser()->getID() : 0;

            if (!isset($options['revert']) || !$options['revert'])
            {
                $revision = ArticleHistory::getTable()->addArticleHistory($this->_name, $this->_old_content, $this->_content, $user_id, $reason);
            }
            else
            {
                $revision = null;
            }

            ArticleLinks::getTable()->deleteLinksByArticle($this->_name);
            ArticleCategories::getTable()->deleteCategoriesByArticle($this->_name);

            $this->save();

            $this->_old_content = $this->_content;

            if (mb_substr($this->getContent(), 0, 10) == "#REDIRECT ")
            {
                $content = explode("\n", $this->getContent());
                preg_match('/(\[\[([^\]]*?)\]\])$/im', mb_substr(array_shift($content), 10), $matches);
                if (count($matches) == 3)
                {
                    return;
                }
            }
            list ($links, $categories) = $this->_retrieveLinksAndCategoriesFromContent($options);

            foreach ($links as $link => $occurrences)
            {
                ArticleLinks::getTable()->addArticleLink($this->_name, $link);
            }

            foreach ($categories as $category => $occurrences)
            {
                ArticleCategories::getTable()->addArticleCategory($this->_name, $category, $this->isCategory());
            }

            $this->_history = null;

            TBGEvent::createNew('core', '\thebuggenie\modules\publish\entities\Article::doSave', $this, compact('reason', 'revision', 'user_id'))->trigger();

            return true;
        }

        public function getPostedDate()
        {
            return $this->_date;
        }

        /**
         * Returns the author
         *
         * @return TBGUser
         */
        public function getAuthor()
        {
            if (is_numeric($this->_author))
            {
                try
                {
                    $this->_author = TBGUser::getB2DBTable()->selectById($this->_author);
                }
                catch (Exception $e)
                {
                    $this->_author = null;
                }
            }
            return $this->_author;
        }

        public function setAuthor($author)
        {
            if (is_object($author))
            {
                $author = $author->getID();
            }
            $this->_author = $author;
        }

        /**
         * Compare to revisions of this article, and return the diff output, as well as revision information
         *
         * @param integer $from_revision
         * @param integer $to_revision
         *
         * @return array
         */
        public function compareRevisions($from_revision, $to_revision)
        {
            $content = ArticleHistory::getTable()->getRevisionContentFromArticleName($this->getName(), $from_revision, $to_revision);
            $old_content = htmlspecialchars($content[$from_revision]['new_content']);
            $new_content = htmlspecialchars($content[$to_revision]['new_content']);

            $diff = new TBGTextDiff();
            $result = $diff->stringDiff($old_content, $new_content);
            $changes = $diff->sequentialChanges($result);
            return array($content, $diff->renderDiff($result));
        }

        public function restoreRevision($revision)
        {
            ArticleHistory::getTable()->removeArticleRevisionsSince($this->getName(), $revision);
            $content = ArticleHistory::getTable()->getRevisionContentFromArticleName($this->getName(), $revision);
            $this->setContent($content['new_content']);
            $this->doSave(array('revert' => true));
        }

        public function setRevision($revision = null)
        {
            $content = ArticleHistory::getTable()->getRevisionContentFromArticleName($this->getName(), $revision);
            if (array_key_exists('new_content', $content))
            {
                $this->setContent($content['new_content']);
                $this->_date = $content['date'];
                $this->_author = $content['author'];
            }
            else
            {
                throw new \Exception('No such revision');
            }
        }

        public function getNamespaces()
        {
            if ($this->_namespaces === null)
            {
                $this->_namespaces = array();
                $namespaces = explode(':', $this->getName());
                if (count($namespaces))
                {
                    array_pop($namespaces);
                    $this->_namespaces = $namespaces;
                }
            }
            return $this->_namespaces;
        }

        public function getCombinedNamespaces()
        {
            $namespaces = $this->getNamespaces();
            if (count($namespaces) > 1)
            {
                $composite_ns = '';
                $return_array = array();
                foreach ($namespaces as $namespace)
                {
                    $composite_ns .= ($composite_ns != '') ? ":{$namespace}" : $namespace;
                    $return_array[] = $composite_ns;
                }
                return $return_array;
            }
            else
            {
                return $namespaces;
            }
        }

        /**
         * Populate the files array
         */
        protected function _populateFiles()
        {
            if ($this->_files === null)
            {
                $this->_files = TBGFile::getByArticleID($this->getID());
            }
        }

        /**
         * Return an array with all files attached to this issue
         *
         * @return array
         */
        public function getFiles()
        {
            $this->_populateFiles();
            return $this->_files;
        }

        /**
         * Return an array with all files attached to this issue
         *
         * @return array
         */
        public function countFiles()
        {
            return count($this->getFiles());
        }

        /**
         * Return a file by the filename if it is attached to this issue
         *
         * @param string $filename The original filename to match against
         *
         * @return TBGFile
         */
        public function getFileByFilename($filename)
        {
            foreach ($this->getFiles() as $file_id => $file)
            {
                if (mb_strtolower($filename) == mb_strtolower($file->getOriginalFilename()))
                {
                    return $file;
                }
            }
            return null;
        }

        /**
         * Attach a file to the issue
         *
         * @param TBGFile $file The file to attach
         */
        public function attachFile(TBGFile $file)
        {
            ArticleFiles::getTable()->addByArticleIDandFileID($this->getID(), $file->getID());
            if ($this->_files !== null)
            {
                $this->_files[$file->getID()] = $file;
            }
        }

        /**
         * Remove a file
         *
         * @param TBGFile $file The file to be removed
         *
         * @return boolean
         */
        public function removeFile(TBGFile $file)
        {
            ArticleFiles::getTable()->removeByArticleIDandFileID($this->getID(), $file->getID());
            if (is_array($this->_files) && array_key_exists($file->getID(), $this->_files))
            {
                unset($this->_files[$file->getID()]);
            }
            $file->delete();
        }

        public function canDelete()
        {
            $namespaces = $this->getNamespaces();

            if (count($namespaces) > 0)
            {
                $key = $namespaces[0];
                $project = TBGProject::getByKey($key);
                if ($project instanceof TBGProject)
                {
                    if ($project->isArchived())
                        return false;
                }
            }

            return TBGContext::getModule('publish')->canUserDeleteArticle($this->getName());
        }

        public function canEdit()
        {
            $namespaces = $this->getNamespaces();

            if (count($namespaces) > 0)
            {
                $key = $namespaces[0];
                $project = TBGProject::getByKey($key);
                if ($project instanceof TBGProject)
                {
                    if ($project->isArchived())
                        return false;
                }
            }

            return TBGContext::getModule('publish')->canUserEditArticle($this->getName());
        }

        public function canRead()
        {
            return TBGContext::getModule('publish')->canUserReadArticle($this->getName());
        }

        public function getProject()
        {
            $namespaces = $this->getNamespaces();

            if (count($namespaces) > 0)
            {
                $key = $namespaces[0];
                $project = TBGProject::getByKey($key);
                return $project;
            }
        }

        public function hasAccess()
        {
            $project = $this->getProject();

            if ($project instanceof TBGProject && $project->isArchived())
                return false;

            return $this->canRead();
        }

        /**
         * Return the items name
         *
         * @return string
         */
        public function getName()
        {
            return $this->_name;
        }

        /**
         * Set the edition name
         *
         * @param string $name
         */
        public function setName($name)
        {
            $this->_name = preg_replace("/[^A-Za-z0-9 :]/", '', $name);
        }

        /**
         * Return the items name
         *
         * @return string
         */
        public function getManualName()
        {
            return $this->_manual_name;
        }

        /**
         * Set the edition name
         *
         * @param string $name
         */
        public function setManualName($name)
        {
            $this->_manual_name = $name;
        }

        public function setParentArticle($parent_article)
        {
            $this->_parent_article_id = $parent_article;
        }

        /**
         * Return the parent article (if any)
         *
         * @return \thebuggenie\modules\publish\entities\Article
         */
        public function getParentArticle()
        {
            return $this->_b2dbLazyload('_parent_article_id');
        }

        public function getParentArticleName()
        {
            $article = $this->getParentArticle();
            return ($article instanceof Article) ? $article->getName() : null;
        }

        public function getChildArticles()
        {
            return $this->_b2dbLazyload('_child_articles');
        }

        public function setArticleType($article_type)
        {
            $this->_article_type = $article_type;
        }

        public function getArticleType()
        {
            return $this->_article_type;
        }

        public function getHistoryUserIDs()
        {
            static $uids = null;
            if ($uids === null)
                $uids = ArticleHistory::getTable()->getUserIDsByArticleName($this->getName());

            return $uids;
        }

        public function getSubscribers()
        {
            $this->_b2dbLazyload('_subscribers');
            return $this->_subscribers;
        }

        public function addSubscriber($user_id)
        {
            UserArticles::getTable()->addStarredArticle($user_id, $this->getID());
        }

    }
