<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/

/** BuildError */
class builderror
{
    public $Type;
    public $LogLine;
    public $Text;
    public $SourceFile;
    public $SourceLine;
    public $PreContext;
    public $PostContext;
    public $RepeatCount;
    public $BuildId;

    // Insert in the database (no update possible)
    public function Insert()
    {
        if (!$this->BuildId) {
            echo 'BuildError::Insert(): BuildId not set<br>';
            return false;
        }

        $text = pdo_real_escape_string($this->Text);

        if (strlen($this->PreContext) == 0) {
            $precontext = 'NULL';
        } else {
            $precontext = "'" . pdo_real_escape_string($this->PreContext) . "'";
        }

        if (strlen($this->PostContext) == 0) {
            $postcontext = 'NULL';
        } else {
            $postcontext = "'" . pdo_real_escape_string($this->PostContext) . "'";
        }

        if (empty($this->SourceLine)) {
            $this->SourceLine = 0;
        }
        if (empty($this->RepeatCount)) {
            $this->RepeatCount = 0;
        }

        $crc32 = 0;
        // Compute the crc32
        if ($this->SourceLine == 0) {
            $crc32 = crc32($text); // no need for precontext or postcontext, this doesn't work for parallel build
        } else {
            $crc32 = crc32($text . $this->SourceFile . $this->SourceLine); // some warning can be on the same line
        }

        $query = 'INSERT INTO builderror (buildid,type,logline,text,sourcefile,sourceline,precontext,
                                      postcontext,repeatcount,newstatus,crc32)
              VALUES (' . qnum($this->BuildId) . ',' . qnum($this->Type) . ',' . qnum($this->LogLine) . ",'$text','$this->SourceFile'," . qnum($this->SourceLine) . ',
              ' . $precontext . ',' . $postcontext . ',' . qnum($this->RepeatCount) . ',0,' . qnum($crc32) . ')';
        if (!pdo_query($query)) {
            add_last_sql_error('BuildError Insert', 0, $this->BuildId);
            return false;
        }
        return true;
    }

    public static function GetSourceFile($data) {
        // Detect if the source directory has already been replaced by CTest with /.../
        $sourceFile = array();
        $pattern = '&/.../(.*?)/&';
        $matches = array();
        preg_match($pattern, $data['text'], $matches);

        if (sizeof($matches) > 1) {
            $sourceFile['file'] = $data['sourcefile'];
            $sourceFile['directory'] = $matches[1];
        } else {
            $sourceFile['file'] = basename($data['sourcefile']);
            $sourceFile['directory'] = dirname($data['sourcefile']);
        }

        return $sourceFile;
    }

    // Ideally $data would be loaded into $this
    // need an id field?
    /**
     * Marshals the data of a particular build error into a serializable
     * friendly format.
     *
     * Requires the $data of a build error, the $project, and the buildupdate.revision.
     **/
    public static function marshal($data, $project, $revision)
    {
        // Sets up access to $file and $directory
        extract(self::GetSourceFile($data));
        return array(
            'new' => (isset($data['newstatus'])) ? $data['newstatus'] : -1,
            'logline' => $data['logline'],
            'cvsurl' => get_diff_url($project['id'], $data['cvsurl'], $directory, $file, $revision),
            // when building without launchers, CTest truncates the source dir to /.../
            // use this pattern to linkify compiler output.
            'precontext' => linkify_compiler_output($data['cvsurl'], "/\.\.\.", $revision, $data['precontext']),
            'text' => linkify_compiler_output($data['cvsurl'], "/\.\.\.", $revision, $data['text']),
            'postcontext' => linkify_compiler_output($data['cvsurl'], "/\.\.\.", $revision, $data['postcontext']),
            'sourcefile' => $data['sourcefile'],
            'sourceline' => $data['sourceline']
        );
    }
}
