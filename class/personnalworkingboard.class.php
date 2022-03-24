<?php

if (!class_exists('TObjetStd'))
{
	/**
	 * Needed if $form->showLinkedObjectBlock() is call
	 */
	define('INC_FROM_DOLIBARR', true);
	require_once dirname(__FILE__).'/../config.php';
}

require_once DOL_DOCUMENT_ROOT.'/core/class/workboardresponse.class.php';

class TPersonnalWorkingBoard extends TObjetStd
{
	public $TDashboardLine = array();

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Renvoi sous forme de chaine le contenu de la boite à afficher
	 *
	 * @param string	$mode	'filtered' or 'global'
	 * @return string
	 */
	public function getContentDolibarrStyle($mode='filtered')
	{
		global $conf, $user, $langs;

		$rights_value = $user->rights->societe->client->voir;

		if (!empty($rights_value) && $mode === 'global') return '';
		elseif (empty($rights_value) && $mode === 'filtered') return '';

		if (!empty($rights_value)) $user->rights->societe->client->voir = 0;
		else
		{
			if (empty($user->rights->societe->client)) $user->rights->societe->client = new stdClass();
			$user->rights->societe->client->voir = 1;
		}

		$TDashboardLine = $this->load_all_board();

		$user->rights->societe->client->voir = $rights_value;

		// copié/collé du fichier htdocs/index.php vers la ligne 550 depuis une 6.0
		$boxwork='';
		// Show dashboard
		$nbworkboardempty=0;
		foreach($TDashboardLine as &$board)
		{
			if (empty($board->nbtodo)) $nbworkboardempty++;

			$textlate = $langs->trans("NActionsLate",$board->nbtodolate);
			$textlate.= ' ('.$langs->trans("Late").' = '.$langs->trans("DateReference").' > '.$langs->trans("DateToday").' '.(ceil($board->warning_delay) >= 0 ? '+' : '').ceil($board->warning_delay).' '.$langs->trans("days").')';

			$boxwork .='<div class="boxstatsindicator thumbstat150 nobold nounderline"><div class="boxstats130 boxstatsborder">';
			$boxwork .= '<div class="boxstatscontent">';
			$boxwork .= '<span class="boxstatstext" title="'.dol_escape_htmltag($board->label).'">'.$board->img.' '.$board->label.'</span><br>';
			$boxwork .= '<a class="valignmiddle dashboardlineindicator" href="'.$board->url.'"><span class="dashboardlineindicator'.(($board->nbtodo == 0)?' dashboardlineok':'').'">'.$board->nbtodo.'</span></a>';
			$boxwork .= '</div>';
			if ($board->nbtodolate > 0)
			{
				$boxwork .= '<div class="dashboardlinelatecoin nowrap">';
				$boxwork .= '<a title="'.dol_escape_htmltag($textlate).'" class="valignmiddle dashboardlineindicatorlate'.($board->nbtodolate>0?' dashboardlineko':' dashboardlineok').'" href="'.((!$board->url_late) ? $board->url : $board->url_late ).'">';
				//$boxwork .= img_picto($textlate, "warning_white", 'class="valigntextbottom"').'';
				$boxwork .= img_picto($textlate, "warning_white", 'class="valigntextbottom"').'';
				$boxwork .= '<span class="dashboardlineindicatorlate'.($board->nbtodolate>0?' dashboardlineko':' dashboardlineok').'">';
				$boxwork .= $board->nbtodolate;
				$boxwork .= '</span>';
				$boxwork .= '</a>';
				$boxwork .= '</div>';
			}
			$boxwork.='</div></div>';
			$boxwork .="\n";
		}

		// Dolibarr style : ça permet d'aligner la dernière ligne comme souhaité (tjr issue du copié/collé)
		$boxwork .='<div class="boxstatsindicator thumbstat150 nobold nounderline"></div>';
		$boxwork .='<div class="boxstatsindicator thumbstat150 nobold nounderline"></div>';
		$boxwork .='<div class="boxstatsindicator thumbstat150 nobold nounderline"></div>';
		$boxwork .='<div class="boxstatsindicator thumbstat150 nobold nounderline"></div>';
		$boxwork .='<div class="boxstatsindicator thumbstat150 nobold nounderline"></div>';
		$boxwork .='<div class="boxstatsindicator thumbstat150 nobold nounderline"></div>';

		return $boxwork;
	}

	public function getMeteoContent()
	{
		global $conf,$langs;

		$boxwork = '';
		if (empty($conf->global->MAIN_DISABLE_METEO) && !empty($this->TDashboardLine))
		{
			$valid_dashboardlines=array();
			foreach($this->TDashboardLine as $tmp)
			{
				if ($tmp instanceof WorkboardResponse) $valid_dashboardlines[] = $tmp;
			}

			// We calculate $totallate. Must be defined before start of next loop because it is show in first fetch on next loop
			foreach($valid_dashboardlines as $board)
			{
				if ($board->nbtodolate > 0) {
					$totallate += $board->nbtodolate;
				}
			}

			$text='';
			if ($totallate > 0) $text=$langs->transnoentitiesnoconv("WarningYouHaveAtLeastOneTaskLate").' ('.$langs->transnoentitiesnoconv("NActionsLate",$totallate).')';
			$text.='. '.$langs->trans("LateDesc");
			//$text.=$form->textwithpicto('',$langs->trans("LateDesc"));
			$options='height="64px"';
			$boxwork.=showWeather($totallate,$text,$options);
		}

		return $boxwork;
	}

	public function load_all_board()
	{
		global $conf,$user;
//		dol_include_once('/core/class/workboardresponse.class.php');
		$TDashboardLine = array();

		if (! empty($conf->agenda->enabled) && $user->rights->agenda->myactions->read)
		{
			$TDashboardLine[] = $this->load_board_actioncomm($user);
		}

		// Number of project opened
		if (! empty($conf->projet->enabled) && $user->rights->projet->lire)
		{
			$TDashboardLine[] = $this->load_board_project($user);
		}

		// Number of tasks to do (late)
		if (! empty($conf->projet->enabled) && empty($conf->global->PROJECT_HIDE_TASKS) && $user->rights->projet->lire)
		{
			$TDashboardLine[] = $this->load_board_task($user);
		}

		// Number of commercial proposals opened (expired)
		if (! empty($conf->propal->enabled) && $user->rights->propale->lire)
		{
			$TDashboardLine[] = $this->load_board_propal($user, 'opened');
			$TDashboardLine[] = $this->load_board_propal($user, 'signed');
		}

		// Number of commercial proposals opened (expired)
		if (! empty($conf->supplier_proposal->enabled) && $user->rights->supplier_proposal->lire)
		{
			$TDashboardLine[] = $this->load_board_supplierproposal($user, 'opened');
			$TDashboardLine[] = $this->load_board_supplierproposal($user, 'signed');
		}

		// Number of customer orders a deal
		if (! empty($conf->commande->enabled) && $user->rights->commande->lire)
		{
			$TDashboardLine[] = $this->load_board_commande($user);
		}

		// Number of suppliers orders a deal
		if (! empty($conf->supplier_order->enabled) && $user->rights->fournisseur->commande->lire)
		{
			$TDashboardLine[] = $this->load_board_commandefournisseur($user);
		}

		// Number of services enabled (delayed)
		if (! empty($conf->contrat->enabled) && $user->rights->contrat->lire)
		{
			$inactive = ((float) DOL_VERSION < 9 ? 'inactives' : 'inactive');
			$TDashboardLine[] = $this->load_board_contrat($user, $inactive);
			$TDashboardLine[] = $this->load_board_contrat($user, 'expired');
		}
		// Number of invoices customers (has paid)
		if (! empty($conf->facture->enabled) && $user->rights->facture->lire)
		{
			$TDashboardLine[] = $this->load_board_facture($user);
		}

		// Number of supplier invoices (has paid)
		if (! empty($conf->supplier_invoice->enabled) && ! empty($user->rights->fournisseur->facture->lire))
		{
			$TDashboardLine[] = $this->load_board_facturefournisseur($user);
		}

		// Number of transactions to conciliate
		if (! empty($conf->banque->enabled) && $user->rights->banque->lire && ! $user->societe_id)
		{
			$Tab = $this->load_board_account($user);
			if (!empty($Tab)) $TDashboardLine[] = $Tab;
		}

		// Number of cheque to send
		if (! empty($conf->banque->enabled) && $user->rights->banque->lire && ! $user->societe_id && empty($conf->global->BANK_DISABLE_CHECK_DEPOSIT))
		{
			$TDashboardLine[] = $this->load_board_remisecheque($user);
		}

		// Number of foundation members
		if (! empty($conf->adherent->enabled) && $user->rights->adherent->lire && ! $user->societe_id)
		{
			$TDashboardLine[] = $this->load_board_adherent($user);
		}

		// Number of expense reports to approve
		if (! empty($conf->expensereport->enabled) && $user->rights->expensereport->approve)
		{
			$TDashboardLine[] = $this->load_board_expensereport($user, 'toapprove');
		}

		// Number of expense reports to pay
		if (! empty($conf->expensereport->enabled) && $user->rights->expensereport->to_paid)
		{
			$TDashboardLine[] = $this->load_board_expensereport($user, 'topay');
		}

		$this->TDashboardLine = $TDashboardLine;
		return $TDashboardLine;
	}


	private function load_board_actioncomm($user)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		$board=new ActionComm($db);
		return $board->load_board($user);

	}


	private function load_board_project($user)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
		$board=new Project($db);
		return $board->load_board($user);
	}


	private function load_board_task($user)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
		$board=new Task($db);
		return $board->load_board($user);
	}


	private function load_board_propal($user, $mode)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/comm/propal/class/propal.class.php';
		$board=new Propal($db);
		return $board->load_board($user, $mode);
	}


	private function load_board_supplierproposal($user, $mode)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/supplier_proposal/class/supplier_proposal.class.php';
		$board=new SupplierProposal($db);
		return $board->load_board($user, $mode);
	}


	private function load_board_commande($user)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		$board=new Commande($db);
		return $board->load_board($user);
	}


	private function load_board_commandefournisseur($user)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
		$board=new CommandeFournisseur($db);
		return $board->load_board($user);
	}


	private function load_board_contrat($user, $mode)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
		$board=new Contrat($db);
		return $board->load_board($user, $mode);
	}


	private function load_board_facture($user)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
		$board=new Facture($db);
		return $board->load_board($user);
	}


	private function load_board_facturefournisseur($user)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
		$board=new FactureFournisseur($db);
		return $board->load_board($user);
	}


	private function load_board_account($user)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
		$board=new Account($db);
		$nb = $board->countAccountToReconcile();    // Get nb of account to reconciliate
		if ($nb > 0)
		{
			return $board->load_board($user);
		}

		return false;
	}


	private function load_board_remisecheque($user)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/compta/paiement/cheque/class/remisecheque.class.php';
		$board=new RemiseCheque($db);
		return $board->load_board($user);
	}


	private function load_board_adherent($user)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
		$board=new Adherent($db);
		return $board->load_board($user);
	}


	private function load_board_expensereport($user, $mode)
	{
		global $db;

		include_once DOL_DOCUMENT_ROOT.'/expensereport/class/expensereport.class.php';
		$board=new ExpenseReport($db);
		return $board->load_board($user, $mode);
	}
}
