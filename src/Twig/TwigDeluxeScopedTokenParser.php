<?php

namespace Drupal\twig_deluxe\Twig;

use Drupal\twig_deluxe\Twig\Node\Scoped;
use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Implements a token parser for the "scoped" tag in Twig templates.
 */
class TwigDeluxeScopedTokenParser extends AbstractTokenParser {

  /**
   * Parses a token and returns a Node.
   *
   * @param \Twig\Token $token
   *   The token to parse.
   *
   * @return \Twig\Node\Node
   *   The parsed node.
   *
   * @throws \Twig\Error\SyntaxError
   */
  public function parse(Token $token): Node {
    $lineno = $token->getLine();
    $stream = $this->parser->getStream();

    // Consume the 'scoped' token.
    $stream->expect(Token::BLOCK_END_TYPE);

    // Parse the body.
    $body = $this->parser->subparse([$this, 'decideScopedEnd'], TRUE);

    // Consume the 'endscoped' token.
    $stream->expect(Token::BLOCK_END_TYPE);

    return new Scoped($body, $lineno, NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function getTag(): string {
    return 'scoped';
  }

  /**
   * Decides whether to end the scoped block.
   *
   * @param \Twig\Token $token
   *   The token to test.
   *
   * @return bool
   *   TRUE if the token is 'endscoped', FALSE otherwise.
   */
  public function decideScopedEnd(Token $token): bool {
    return $token->test('endscoped');
  }

}
