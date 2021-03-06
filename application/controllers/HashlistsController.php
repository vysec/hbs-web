<?php
/**
 * @package HashBruteStation
 * @see for EN http://hack4sec.pro/wiki/index.php/Hash_Brute_Station_en
 * @see for RU http://hack4sec.pro/wiki/index.php/Hash_Brute_Station
 * @license MIT
 * @copyright (c) Anton Kuzmin <http://anton-kuzmin.ru> (ru) <http://anton-kuzmin.pro> (en)
 * @author Anton Kuzmin
 */
class HashlistsController extends Zend_Controller_Action
{
    public function init()
    {
        parent::init();
        $this->_model = new Hashlists();
    }

    public function indexAction() {
        if ($this->_getParam('err')) {
            $this->view->err = "L_HASHLIST_NOW_IN_USE";
        }
        $this->view->list = $this->_model->fetchAll("!common_by_alg", "name ASC");
        $this->view->common_list = $this->_model->fetchAll("common_by_alg", "name ASC");
    }

    public function addAction() {
        $this->view->settings = Utils::getSettings();
        $form = new Forms_Hashlists_Add();
        if ($this->_request->isPost() and $form->isValid($_POST) and $form->file->receive()) {
            $_POST['filepath'] = $form->file->getFileName(NULL, false);
            $this->_model->add($_POST);
            $this->redirect('/hashlists/');
            exit;
        } else {
            $this->view->form = $form;
        }
    }
    public function editAction() {
        $form = new Forms_Hashlists_Edit();
        $form->name->getValidator('Forms_Validate_Hashlists_Name')->setExcludeId($this->_getParam('id'));
        if ($this->_request->isPost() and $form->isValid($_POST)) {
            $this->_model->edit($_POST);
            $this->redirect('/hashlists/');
        } else {
            $form->populate($this->_model->get($this->_getParam('id'))->toArray());
            $this->view->form = $form;
            $this->view->hashlist = $this->_model->get($this->_getParam('id'));
        }
    }

    public function deleteAction() {
        if ($this->_model->getWorkHashlist() == $this->_getParam('id')) {
            $this->redirect('/hashlists/?err=1');
        } else {
            $this->_model->get($this->_getParam('id'))->delete();
            $this->redirect('/hashlists/');
        }
    }

    public function inAction() {
        $this->view->hashlist = $this->_model->get($this->_getParam('id'));
        $form = new Forms_Hashlists_In();
        $form->setId($this->_getParam('id'));
        $this->view->settings = Utils::getSettings();
        if ($this->_request->isPost() and $form->isValid($_POST) and $form->file->receive()) {
            $_POST['filepath'] = $form->file->getFileName(NULL, false);
            $this->_model->in($_POST);
            $this->redirect('/hashlists/');
        } else {
            $this->view->form = $form;
        }
    }

    public function importAction() {
        $this->view->hashlist = $this->_model->get($this->_getParam('id'));
        $form = new Forms_Hashlists_Import();
        $form->setId($this->_getParam('id'));

        if ($this->_request->isPost() and $form->isValid($_POST)) {
            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Pragma', 'public', true)
                ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
                ->setHeader('Content-type', 'application/unknown;charset=utf-8', true)
                ->setHeader('Content-Disposition', "attachment; filename=hashes-import.txt")
                ->clearBody();
            $this->getResponse()->sendHeaders();

            $stmt = $this->_model->import($this->_getParam('id'), $this->_getParam('founded'));
            while ($row = $stmt->fetch()) {
                $str = $row['hash'];
                if ($this->_getParam('salts')) {
                    $str .= $this->_getParam('delimiter') . $row['salt'];
                }
                if ($this->_getParam('founded')) {
                    $str .= $this->_getParam('delimiter') . $row['password'];
                }
                $str .= "\n";
                print $str;
            }
            exit;
        } else {
            $this->view->form = $form;
        }
    }

    public function errorsAction() {
        die(nl2br($this->_model->get($this->_getParam('id'))->errors));
    }

    public function getHashlistsJsonDataAction() {
        $t = Zend_Registry::get('Zend_Translate');

        $result = [];
        $hashlists = $this->_model->fetchAll(null, "name ASC");
        foreach ($hashlists as $hashlist) {
            $result[$hashlist->id] = [
                'id' => $hashlist->id,
                'cracked' => $hashlist->getCounts()['cracked'],
                'not_cracked' => $hashlist->getCounts()['not_cracked'],
                'cracked_percents' => $hashlist->getCounts()['cracked_percents'],
                'not_cracked_percents' => $hashlist->getCounts()['not_cracked_percents'],
                'status' => $t->translate('L_HASHLIST_STATUS_' . strtoupper($hashlist->status))
            ];
        }

        $this->_helper->json($result);
    }
}