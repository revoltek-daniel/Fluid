<?php
namespace TYPO3\Fluid\Core\Parser\Interceptor;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3\Fluid\Core\Parser\InterceptorInterface;
use TYPO3\Fluid\Core\Parser\ParsingState;
use TYPO3\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3\Fluid\Core\Parser\SyntaxTree\ObjectAccessorNode;
use TYPO3\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3\Fluid\Core\ViewHelper\ViewHelperResolver;

/**
 * An interceptor adding the "Htmlspecialchars" viewhelper to the suitable places.
 */
class Escape implements InterceptorInterface {

	/**
	 * Is the interceptor enabled right now for child nodes?
	 *
	 * @var boolean
	 */
	protected $childrenEscapingEnabled = TRUE;

	/**
	 * A stack of ViewHelperNodes which currently disable the interceptor.
	 * Needed to enable the interceptor again.
	 *
	 * @var NodeInterface[]
	 */
	protected $viewHelperNodesWhichDisableTheInterceptor = array();

	/**
	 * Adds a ViewHelper node using the Format\HtmlspecialcharsViewHelper to the given node.
	 * If "escapingInterceptorEnabled" in the ViewHelper is FALSE, will disable itself inside the ViewHelpers body.
	 *
	 * @param NodeInterface $node
	 * @param integer $interceptorPosition One of the INTERCEPT_* constants for the current interception point
	 * @param ParsingState $parsingState the current parsing state. Not needed in this interceptor.
	 * @return NodeInterface
	 */
	public function process(NodeInterface $node, $interceptorPosition, ParsingState $parsingState) {
		$resolver = $parsingState->getViewHelperResolver();
		if ($interceptorPosition === InterceptorInterface::INTERCEPT_OPENING_VIEWHELPER) {
			/** @var ViewHelperNode $node */
			if (!$node->getUninitializedViewHelper()->isChildrenEscapingEnabled()) {
				$this->childrenEscapingEnabled = FALSE;
				$this->viewHelperNodesWhichDisableTheInterceptor[] = $node;
			}
		} elseif ($interceptorPosition === InterceptorInterface::INTERCEPT_CLOSING_VIEWHELPER) {
			if (end($this->viewHelperNodesWhichDisableTheInterceptor) === $node) {
				array_pop($this->viewHelperNodesWhichDisableTheInterceptor);
				if (count($this->viewHelperNodesWhichDisableTheInterceptor) === 0) {
					$this->childrenEscapingEnabled = TRUE;
				}
			}
			/** @var ViewHelperNode $node */
			if ($this->childrenEscapingEnabled && $node->getUninitializedViewHelper()->isOutputEscapingEnabled()) {
				$node = $this->wrapNode($node, $resolver, $parsingState);
			}
		} elseif ($this->childrenEscapingEnabled && $node instanceof ObjectAccessorNode) {
			$node = $this->wrapNode($node, $resolver, $parsingState);
		}
		return $node;
	}

	/**
	 * @param NodeInterface $node
	 * @param ViewHelperResolver $viewHelperResolver
	 * @param ParsingState $state
	 * @return ViewHelperNode
	 */
	protected function wrapNode(NodeInterface $node, ViewHelperResolver $viewHelperResolver, ParsingState $state) {
		return new ViewHelperNode($viewHelperResolver, 'f', 'format.htmlspecialchars', array('value' => $node), $state);
	}

	/**
	 * This interceptor wants to hook into object accessor creation, and opening / closing ViewHelpers.
	 *
	 * @return array Array of INTERCEPT_* constants
	 */
	public function getInterceptionPoints() {
		return array(
			InterceptorInterface::INTERCEPT_OPENING_VIEWHELPER,
			InterceptorInterface::INTERCEPT_CLOSING_VIEWHELPER,
			InterceptorInterface::INTERCEPT_OBJECTACCESSOR
		);
	}
}