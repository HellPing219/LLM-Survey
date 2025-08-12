# Prompt Codes

Use this list to create the placeholders for the prompt.

## Normal questions

To insert an answer of a normal question, simply use the questions code:

```
A user is [age] years old and...
```

## Subquestions

To insert an answer of a subquestion, combine the parents question code and the subquestions code:

**Example:** You have an array question with the code `eval` where a user has to give a score to multiple items.

```
The user gave this item a score of [eval_item1] / 10.
```

## Comment questions

To insert the comment of a comment question, append 'comment' to the end of the question code:

**Example:** You have a question where a user has to choose their favorite color from a list.

```
The user chose [colorList] as their favorite color and wrote [colorList_comment] as a comment.
```

## Comment subquestions

To insert the comment of a comment subquestion, append 'comment' to the subquestion code:

```
From a list of red, green and blue a user chose the following:
    red: [colorList_red] with comment [colorList_redcomment],
    green: [colorList_green] with comment [colorList_greencomment],
    blue: [colorList_blue] with comment [colorList_bluecomment].
```

## Ranking

To get the items at a specific rank from the ranking question type:

```
The user chose [rankQuestion_1] as rank 1 and [rankQuestion_2] as rank 2 ...
```

## Array Numbers and Array Texts

These questions combine two subquestions to select the field. The structure looks as follows:

```
[qCode_sqY1_sqX1] [qCode_sqY1_sqX2]
[qCode_sqY2_sqX1] [yCode_sqY2_sqX2]
```

## Array Dual Scale

To get the answer to the subquestion for the first scale, simply use the structure `[qCode_sqCode]`.
However the answer to the second scale is stored in a completely different format. To access it use the following structure:
`[{surveyId}X{questionGroupId}X{questionId}{subquestionCode}#1]`

- surveyId is the 6 digit number of the survey
- questionGroupId and questionId can be found when clicking on the question in the structure tab of the survey
- subquestionCode is the code you'd normally use

**Example:** `[592886X51X517sq001#1]`

## Meta Fields

Every survey has a field `[startlanguage]` which stores the language the user has selected for the survey. It can also be used in a prompt.
