<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* javascript/redirect.twig */
class __TwigTemplate_0cbc0cf44bfadaf072d35231d431c6aed15b3800d4b7409e28a810e3fbef02d7 extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        echo "<script type='text/javascript'>
    window.onload = function () {
        window.location = '";
        // line 3
        echo twig_escape_filter($this->env, ($context["url"] ?? null), "html", null, true);
        echo "';
    };
</script>
";
    }

    public function getTemplateName()
    {
        return "javascript/redirect.twig";
    }

    public function isTraitable()
    {
        return false;
    }

    public function getDebugInfo()
    {
        return array (  41 => 3,  37 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "javascript/redirect.twig", "/home/joeunfish.online/public_html/dbeditor/templates/javascript/redirect.twig");
    }
}
