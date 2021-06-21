<?php


class TaskException extends Exception
{
}

class Task
{
    private $_id;
    private $_title;
    private $_description;
    private $_deadline;
    private $_completed;

    /**
     * Task constructor.
     * @param $id
     * @param $title
     * @param $description
     * @param $deadline
     * @param $completed
     */
    public function __construct($id, $title, $description, $deadline, $completed)
    {
        $this->setId($id);
        $this->setTitle($title);
        $this->setDescription($description);
        $this->setDeadline($deadline);
        $this->setCompleted($completed);
    }


    public function getId()
    {
        return $this->_id;
    }

    public function getTitle()
    {
        return $this->_title;
    }

    public function getDescription()
    {
        return $this->_description;
    }

    public function getDeadline()
    {
        return $this->_deadline;
    }

    public function getCompleted()
    {
        return $this->_completed;
    }

    /**
     * @throws TaskException
     */
    public function setId($id)
    {
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new TaskException("Task Id Error");
        }
        $this->_id = $id;
    }

    /**
     * @throws TaskException
     */
    public function setTitle($title)
    {
        if (strlen($title) < 0 || strlen($title) > 255) {
            throw new TaskException("Task title Error");
        }
        $this->_title = $title;
    }

    /**
     * @throws TaskException
     */
    public function setDescription($description)
    {
        if (($description !== null) && (strlen($description) > 16777215)) {
            throw new TaskException("Task description Error");
        }
        $this->_description = $description;
    }

    /**0
     * @throws TaskException
     */
    public function setDeadline($deadline)
    {
        if (!date_create_from_format("d/m/Y H:i", $deadline)) {
            $date = null;
        } else {
            $date = date_format(date_create_from_format("d/m/Y H:i", $deadline), "d/m/Y H:i");

        }
        if (($deadline !== null) && $date !== $deadline) {
            throw new TaskException("Task deadline date time Error  $deadline and $date");
        }
        $this->_deadline = $deadline;
    }

    /**
     * @throws TaskException
     */
    public function setCompleted($completed)
    {
        if (strtoupper($completed) !== "Y" && strtoupper($completed) !== "N") {
            throw new TaskException("Task must be completed or not (Y or N)");
        }
        $this->_completed = $completed;
    }

    public function getTaskArray()
    {
        $task = array();
        $task['id'] = $this->getId();
        $task['title'] = $this->getTitle();
        $task['description'] = $this->getDescription();
        $task['deadline'] = $this->getDeadline();
        $task['completed'] = $this->getCompleted();


        return $task;
    }
}